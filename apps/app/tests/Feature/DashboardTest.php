<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
