<?php

namespace Tests\Feature\Auth;

use App\Enums\Auth\AccountLifecycle;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use App\Notifications\UserPasswordChanged;
use App\Services\UserManagement\UserAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Ninja\DeviceTracker\Modules\Location\Contracts\LocationProvider;
use Ninja\DeviceTracker\Modules\Location\DTO\Location;
use Tests\TestCase;

class PersonAuthenticationR13VerificationTest extends TestCase
{
    use RefreshDatabase;

    private UserAccountService $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = app(UserAccountService::class);
        config([
            'devices.user_agent_whitelist' => array_values(array_unique(array_merge(
                (array) config('devices.user_agent_whitelist', []),
                ['Symfony']
            ))),
            'devices.google_2fa_enabled' => false,
        ]);
        if (interface_exists(LocationProvider::class)) {
            $this->app->instance(
                LocationProvider::class,
                new class implements LocationProvider
                {
                    public function country(): ?string { return null; }
                    public function region(): ?string { return null; }
                    public function city(): ?string { return null; }
                    public function postal(): ?string { return null; }
                    public function latitude(): ?string { return null; }
                    public function longitude(): ?string { return null; }
                    public function timezone(): ?string { return null; }
                    public function locate(string $ip): Location { return Location::fromArray(['ip' => $ip]); }
                }
            );
        }
    }

    private function mkUser(string $u, string $p = 'password123', ?string $e = null, bool $fc = false): User
    {
        $person = Profile::factory()->create(array_filter(['email' => $e]));
        return $this->accounts->createForPerson($person, [
            'username' => $u, 'password' => $p,
        ], ['lifecycle' => AccountLifecycle::Active, 'send_notification' => false, 'force_password_change' => $fc]);
    }

    // ===== SECTION 1: PASSWORD EXPOSURE =====

    public function test_admin_reset_notification_no_password_properties(): void
    {
        $ref = new \ReflectionClass(AdminResetPasswordNotification::class);
        foreach ($ref->getProperties() as $prop) {
            $this->assertFalse((bool) preg_match('/password|temp/i', $prop->getName()));
        }
    }

    public function test_user_pw_changed_notification_no_password_properties(): void
    {
        $ref = new \ReflectionClass(UserPasswordChanged::class);
        foreach ($ref->getProperties() as $prop) {
            $this->assertFalse((bool) preg_match('/password|plain/i', $prop->getName()));
        }
    }

    public function test_admin_reset_notification_array_no_password(): void
    {
        $user = $this->mkUser('adm_arr', e: 'adm-arr@test.com');
        $n = new AdminResetPasswordNotification();
        $data = $n->toArray($user);
        $this->assertArrayNotHasKey('temp_password', $data);
        $this->assertArrayNotHasKey('plain_password', $data);
    }

    public function test_reset_password_returns_user_object(): void
    {
        $user = $this->mkUser('ret_user');
        $result = $this->accounts->resetPassword($user);
        $this->assertInstanceOf(User::class, $result);
        $this->assertTrue($result->requiresPasswordChange());
    }

    public function test_legacy_events_no_password_properties(): void
    {
        $classes = [
            \App\Events\UserPasswordResetByAdmin::class,
            \App\Events\UserCreated::class,
            \App\Events\TenantAdminCreated::class,
            \App\Events\Auth\AuthenticationAccountCreated::class,
        ];
        foreach ($classes as $class) {
            $ref = new \ReflectionClass($class);
            foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                $this->assertFalse((bool) preg_match('/password/i', $prop->getName()));
            }
            $ctor = $ref->getConstructor();
            if ($ctor) {
                foreach ($ctor->getParameters() as $param) {
                    $this->assertFalse((bool) preg_match('/password/i', $param->getName()));
                }
            }
        }
    }

    // ===== SECTION 2: ROUTE AUTH =====

    public function test_protected_routes_redirect_unauthenticated(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/password/force')->assertRedirect(route('login'));
        $this->get('/verify-email')->assertRedirect(route('login'));
        $this->get('/confirm-password')->assertRedirect(route('login'));
    }

    public function test_public_auth_routes_accessible(): void
    {
        $this->getJson(route('login'))->assertOk();
        $this->getJson(route('password.request'))->assertOk();
    }

    public function test_logout_requires_auth(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
    }
// ===== SECTION 3: RECOVERY =====

    public function test_recovery_generic_response_prevents_enumeration(): void
    {
        $msg = 'If an eligible account exists for this email, a reset code has been sent.';
        $this->postJson('/forgot-password', ['email' => 'unknown@test.com'])->assertJson(['message' => $msg]);
        $user = $this->mkUser('rec_user', e: 'rec-user@test.com');
        $user->forceFill(['auth_email_verified_at' => now(), 'auth_email_enabled' => true])->save();
        $this->postJson('/forgot-password', ['email' => 'rec-user@test.com'])->assertJson(['message' => $msg]);
    }

    public function test_recovery_otp_is_single_use(): void
    {
        $user = $this->mkUser('single_use', e: 'single-use@test.com');
        $user->forceFill(['auth_email_verified_at' => now(), 'auth_email_enabled' => true])->save();
        $otp = app(\Ichtrojan\Otp\Otp::class)->generate('single-use@test.com', 'numeric', 6, 10);
        $token = $otp->token ?? $otp->otp ?? null;
        $this->assertNotNull($token);
        $this->postJson('/reset-password', [
            'token' => $token, 'email' => 'single-use@test.com',
            'password' => 'new-pw-1!', 'password_confirmation' => 'new-pw-1!',
        ])->assertOk();
        $r2 = $this->postJson('/reset-password', [
            'token' => $token, 'email' => 'single-use@test.com',
            'password' => 'another-pw-2!', 'password_confirmation' => 'another-pw-2!',
        ]);
        $r2->assertStatus(400);
        $this->assertStringContainsString('Invalid or expired OTP', $r2->getContent());
    }

    public function test_recovery_does_not_disable_mfa(): void
    {
        $user = $this->mkUser('mfa_keep', e: 'mfa-keep@test.com');
        $user->forceFill(['auth_email_verified_at' => now(), 'auth_email_enabled' => true])->save();
        $user->createTwoFactorAuth();
        $user->confirmTwoFactorAuth($user->makeTwoFactorCode());
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $otp = app(\Ichtrojan\Otp\Otp::class)->generate('mfa-keep@test.com', 'numeric', 6, 10);
        $token = $otp->token ?? $otp->otp ?? null;
        $this->assertNotNull($token);
        $this->postJson('/reset-password', [
            'token' => $token, 'email' => 'mfa-keep@test.com',
            'password' => 'new-pw-mfa!', 'password_confirmation' => 'new-pw-mfa!',
        ])->assertOk();
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    // ===== SECTION 4: SESSION REVOCATION =====

    public function test_forced_password_change_regenerates_session(): void
    {
        $user = $this->mkUser('sess_reg', fc: true);
        $user->forceFill(['auth_email_verified_at' => now(), 'auth_email_enabled' => true])->save();
        $this->actingAs($user);
        $this->session(['auth.email_otp_confirmed_at' => time()]);
        $old = session()->getId();
        $this->put('/password/force', [
            'password' => 'newSessionPass1!', 'password_confirmation' => 'newSessionPass1!',
        ]);
        $this->assertNotSame($old, session()->getId());
    }

    public function test_forced_password_change_clears_force_flag(): void
    {
        $user = $this->mkUser('flag_clr', fc: true);
        $user->forceFill(['auth_email_verified_at' => now(), 'auth_email_enabled' => true])->save();
        $this->assertTrue($user->requiresPasswordChange());
        $this->actingAs($user->fresh());
        session()->put('auth.email_otp_confirmed_at', time());
        $response = $this->withoutExceptionHandling()->put('/password/force', [
            'password' => 'newSessionPass2!', 'password_confirmation' => 'newSessionPass2!',
        ]);
        $user->refresh();
        $this->assertFalse($user->requiresPasswordChange());
    }

    public function test_force_change_current_session_preserved(): void
    {
        $user = $this->mkUser('sess_keep', fc: true);
        $user->forceFill(['auth_email_verified_at' => now(), 'auth_email_enabled' => true])->save();
        $this->actingAs($user);
        $this->session(['auth.email_otp_confirmed_at' => time()]);
        $this->put('/password/force', [
            'password' => 'newSessionPass3!', 'password_confirmation' => 'newSessionPass3!',
        ]);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_password_update_via_profile_changes_credential(): void
    {
        $user = $this->mkUser('prof_upd', p: 'old-password-123');
        $this->actingAs($user);
        $this->put('/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456!', 'password_confirmation' => 'new-password-456!',
        ]);
        $user->refresh();
        $this->assertTrue(Hash::check('new-password-456!', $user->password));
    }
}