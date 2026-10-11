<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Mail\AccountMessage;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
                'email' => 'test@example.com',
            ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_a_changed_email_gets_a_confirmation_link_and_the_old_address_is_told(): void
    {
        Mail::fake();
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@traceway.test']);

        $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'new@traceway.test',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, VerifyEmail::class);
        Mail::assertQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->hasTo('old@traceway.test')
            && $accountMessage->template === 'email-changed'
            && $accountMessage->values['new_email'] === 'new@traceway.test');
        Mail::assertNotQueued(AccountMessage::class, fn (AccountMessage $accountMessage): bool => $accountMessage->template === 'welcome');
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
}
