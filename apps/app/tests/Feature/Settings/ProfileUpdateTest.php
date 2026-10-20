<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Domain\Courses\Services\CourseServices;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Notifications\ConfirmNewEmail;
use App\Notifications\VerifyEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\RecordingCourseService;
use Tests\TestCase;

final class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $testResponse->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('Test User', $user->refresh()->name);
    }

    public function test_a_new_email_waits_for_its_link_and_the_account_keeps_the_old_one(): void
    {
        Mail::fake();
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@traceway.test']);

        $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'new@traceway.test',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('old@traceway.test', $user->email);
        $this->assertSame('new@traceway.test', $user->pending_email);
        $this->assertInstanceOf(CarbonImmutable::class, $user->email_verified_at);
        Notification::assertSentOnDemand(ConfirmNewEmail::class, fn (ConfirmNewEmail $confirmNewEmail, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'new@traceway.test');
        Notification::assertNotSentTo($user, VerifyEmail::class);
        Mail::assertNothingQueued();
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        Mail::fake();
        Notification::fake();

        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertInstanceOf(CarbonImmutable::class, $user->refresh()->email_verified_at);
        Notification::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $testResponse
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_page_says_whether_a_rider_without_a_password_confirmed_it_is_them(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->where('hasPassword', expected: false)
                ->where('confirmedRecently', expected: false),
            );

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->where('hasPassword', expected: false)
                ->where('confirmedRecently', expected: true),
            );
    }

    public function test_a_rider_without_a_password_is_brought_back_to_profile_after_confirming_it_is_them(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->get(route('profile.confirm'))
            ->assertRedirect(route('password.confirm'));

        $this->assertSame(route('profile.confirm'), session('url.intended'));

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->get(route('profile.confirm'))
            ->assertRedirect(route('profile.edit'));
    }

    public function test_a_rider_without_a_password_confirms_it_is_them_before_deleting_the_account(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'))
            ->assertSessionHasErrors(['password' => 'Confirm it is you first, then delete the account.'])
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_rider_without_a_password_deletes_the_account_once_confirmed_and_the_connections_go_with_it(): void
    {
        $recordingCourseService = new RecordingCourseService('wahoo');
        $this->app?->instance(CourseServices::class, new CourseServices(['wahoo' => $recordingCourseService]));
        $user = User::factory()->create(['password' => null]);
        ConnectedAccount::factory()->for($user)->create(['access_token' => 'wahoo-token']);
        ConnectedAccount::factory()->for($user)->signInOnly('google')->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->delete(route('profile.destroy'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertSame(['wahoo-token'], $recordingCourseService->revoked);
        $this->assertSame(0, ConnectedAccount::query()->count());
    }
}
