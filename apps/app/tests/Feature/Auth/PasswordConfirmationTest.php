<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $testResponse = $this->actingAs($user)->get(route('password.confirm'));

        $testResponse->assertOk();

        $testResponse->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('auth/confirm-password'),
        );
    }

    public function test_password_confirmation_requires_authentication(): void
    {
        $testResponse = $this->get(route('password.confirm'));

        $testResponse->assertRedirect(route('login'));
    }
}
