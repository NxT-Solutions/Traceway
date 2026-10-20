<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\ConnectedAccount;
use App\Models\User;
use Dom\Element;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\Support\DrawnPage;
use Tests\TestCase;

final class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        Features::passkeys([
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->component('settings/security')
                ->where('canManagePasskeys', expected: true)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', expected: true)
                ->where('twoFactorEnabled', expected: false),
            );
    }

    public function test_security_page_requires_password_confirmation_when_enabled(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->create();

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $testResponse = $this->actingAs($user)
            ->get(route('security.edit'));

        $testResponse->assertRedirect(route('password.confirm'));
    }

    public function test_security_page_renders_without_two_factor_when_feature_is_disabled(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        config(['fortify.features' => []]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->component('settings/security')
                ->where('canManagePasskeys', expected: false)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', expected: false)
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation'),
            );
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $testResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $testResponse = $this
            ->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $testResponse
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }

    public function test_a_rider_with_a_password_still_gives_the_current_one_to_change_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', (string) $user->refresh()->password));
    }

    public function test_security_page_offers_a_rider_without_a_password_to_set_one(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->component('settings/security')
                ->where('hasPassword', expected: false),
            );
    }

    public function test_set_a_password_says_only_what_is_true_when_a_passkey_is_the_only_way_in(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());

        $user = User::factory()->create(['password' => null]);
        $this->securityPasskey($user, 'credential-only');

        $testResponse = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->get(route('security.edit'))
            ->assertOk();

        $card = DrawnPage::of($testResponse)->getElementById('security-password');

        $this->assertInstanceOf(Element::class, $card);
        $this->assertSame('Set a password', $card->querySelector('h2')?->textContent);
        $this->assertSame('You do not have a password yet. A password gives you another way to log in.', $card->querySelector('header p')?->textContent);
    }

    public function test_a_rider_without_a_password_sets_one_without_a_current_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', (string) $user->refresh()->password));
    }

    public function test_a_rider_without_a_password_sends_no_current_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'a-guess',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertNull($user->refresh()->password);
    }

    public function test_a_rider_without_a_password_confirms_it_is_them_before_setting_one(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrors(['password' => 'Confirm it is you first, then set a password.'])
            ->assertRedirect(route('security.edit'));

        $this->assertNull($user->refresh()->password);
    }

    public function test_a_rider_without_a_password_keeps_the_passkey_that_is_their_last_way_in(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());

        config(['services.wahoo.client_id' => null]);
        $user = User::factory()->create(['password' => null]);
        $passkey = $this->securityPasskey($user, 'credential-last');
        // A service that is not set up here cannot log anyone in.
        ConnectedAccount::factory()->for($user)->create(['provider_user_id' => '60462']);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
            ->deleteJson(route('passkey.destroy', $passkey))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['passkey' => 'Add a password or connect a service first, so you can still log in.']);

        $this->from(route('security.edit'))
            ->delete(route('passkey.destroy', $passkey))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHasErrors(['passkey' => 'Add a password or connect a service first, so you can still log in.']);

        $this->assertDatabaseHas('passkeys', ['id' => $passkey]);
    }

    public function test_a_rider_removes_a_passkey_while_another_way_in_remains(): void
    {
        $this->skipUnlessFortifyHas(Features::passkeys());

        config(['services.google.client_id' => 'google-id', 'services.google.client_secret' => 'google-secret']);
        $withPassword = User::factory()->create();
        $withAnotherPasskey = User::factory()->create(['password' => null]);
        $withAService = User::factory()->create(['password' => null]);
        ConnectedAccount::factory()->for($withAService)->signInOnly('google')->create(['provider_user_id' => '1001']);
        $this->securityPasskey($withAnotherPasskey, 'credential-spare');

        foreach ([$withPassword, $withAnotherPasskey, $withAService] as $index => $user) {
            $passkey = $this->securityPasskey($user, 'credential-'.$index);

            $this->actingAs($user)
                ->withSession(['auth.password_confirmed_at' => Date::now()->getTimestamp()])
                ->deleteJson(route('passkey.destroy', $passkey))
                ->assertOk();

            $this->assertDatabaseMissing('passkeys', ['id' => $passkey]);
        }
    }

    private function securityPasskey(User $user, string $credentialId): int
    {
        return DB::table('passkeys')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Phone',
            'credential_id' => $credentialId,
            'credential' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
