<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $testResponse = $this->get(route('dashboard'));
        $testResponse->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $testResponse = $this->get(route('dashboard'));
        $testResponse->assertOk();
    }

    public function test_the_planner_starts_each_sport_on_the_same_surface_as_the_ai_connector(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('dashboard')
            ->where('sportSurfaces.road', 'paved')
            ->where('sportSurfaces.trail_run', 'dirt')
            ->where('sportSurfaces.mtb', 'dirt')
            ->where('sportSurfaces.walk', 'mixed')
            ->has('sportSurfaces', 7)
        );
    }

    public function test_the_planner_gets_the_town_typed_on_the_home_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard', ['near' => '  Leuven,   Belgium ']))->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('near', 'Leuven, Belgium')
        );
    }

    public function test_the_planner_leaves_out_a_town_its_search_would_not_take(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertInertia(fn (Assert $assert): Assert => $assert->where('near', expected: null));
        $this->get(route('dashboard', ['near' => 'L']))->assertInertia(fn (Assert $assert): Assert => $assert->where('near', expected: null));
        $this->get(route('dashboard', ['near' => str_repeat('a', 121)]))->assertInertia(fn (Assert $assert): Assert => $assert->where('near', expected: null));
        $this->get(route('dashboard', ['near' => ['Leuven']]))->assertInertia(fn (Assert $assert): Assert => $assert->where('near', expected: null));
        $this->get('/dashboard?near=%C3%28')->assertInertia(fn (Assert $assert): Assert => $assert->where('near', expected: null));
    }

    public function test_the_planner_takes_a_town_as_short_or_as_long_as_its_search_does(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard', ['near' => 'Ay']))->assertInertia(fn (Assert $assert): Assert => $assert->where('near', 'Ay'));
        $this->get(route('dashboard', ['near' => str_repeat('a', 120)]))->assertInertia(fn (Assert $assert): Assert => $assert->where('near', str_repeat('a', 120)));
    }

    public function test_a_new_rider_lands_on_their_town_after_signing_up_and_checking_their_email(): void
    {
        Notification::fake();
        $town = route('dashboard', ['near' => 'Leuven'], absolute: false);

        $this->get($town)->assertRedirect(route('login'));

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect($town);

        $this->get($town)->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        Event::fake([Verified::class]);

        $this->get(URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        ))->assertRedirect($town);
    }
}
