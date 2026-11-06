<?php

namespace Tests\Feature\Auth;

use App\Enums\Auth\AccountLifecycle;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\UserManagement\UserAccountService;
use Ichtrojan\Otp\Otp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * R13 correction suite: real MFA HTTP, School admin HTTP, email OTP HTTP.
 *
 * Rate-limit tests rely on natural Cache/RateLimiter decay via time travel only
 * (no RateLimiter::clear to fake expiry). Safe-device tests require the configured
 * cookie to be issued and resent across sessions.
 */
class PersonAuthenticationR13CorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private UserAccountService $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = app(UserAccountService::class);
    }

    private function makeActiveUser(string $username, string $password = 'password123', ?string $email = null): User
    {
        $person = Profile::factory()->create(array_filter(['email' => $email]));

        return $this->accounts->createForPerson($person, [
            'username' => $username,
            'password' => $password,
        ], [
            'lifecycle' => AccountLifecycle::Active,
            'send_notification' => false,
            'force_password_change' => false,
        ]);
    }

    private function grantPermission(User $user, string $name, ?School $school = null): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_user')) {
            return;
        }
        $permission = Permission::query()->firstOrCreate(
            ['name' => $name],
            ['display_name' => $name]
        );
        DB::table('permission_user')->insertOrIgnore([
            'permission_id' => $permission->id,
            'user_id' => $user->id,
            'user_type' => User::class,
            'school_id' => $school?->id,
        ]);
        DB::table('permission_user')->insertOrIgnore([
            'permission_id' => $permission->id,
            'user_id' => $user->id,
            'user_type' => User::class,
            'school_id' => null,
        ]);
    }


    private function ensureAdminRoleExists(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }
        Role::query()->firstOrCreate(
            ['name' => 'admin', 'school_id' => null],
            ['display_name' => 'Admin']
        );
    }

    private function actingAsSchoolCreator(): User
    {
        $this->ensureAdminRoleExists();
        $user = $this->makeActiveUser('school_creator_'.Str::lower(Str::random(4)));
        $this->grantPermission($user, 'school.create');
        $this->grantPermission($user, 'school.assign-admin');
        if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
            $role = Role::query()->firstOrCreate(
                ['name' => 'super-admin'],
                ['display_name' => 'Super Admin']
            );
            DB::table('role_user')->insertOrIgnore([
                'role_id' => $role->id,
                'user_id' => $user->id,
                'user_type' => User::class,
                'school_id' => null,
            ]);
        }
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    /**
     * @return array{0: User, 1: \Illuminate\Support\Collection<int, string>}
     */
    private function enableMfa(User $user): array
    {
        $user->createTwoFactorAuth();
        $code = $user->makeTwoFactorCode();
        $this->assertTrue($user->confirmTwoFactorAuth($code), 'Failed to enable MFA with generated TOTP');
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());

        $plainCodes = $user->getRecoveryCodes()
            ->map(fn ($row) => is_array($row) ? ($row['code'] ?? null) : $row)
            ->filter()
            ->values();

        if ($plainCodes->isEmpty()) {
            $generated = $user->generateRecoveryCodes();
            $plainCodes = collect($generated)
                ->map(fn ($row) => is_array($row) ? ($row['code'] ?? (string) $row) : (string) $row)
                ->values();
        }

        return [$user->fresh(), $plainCodes];
    }

    private function mfaThrottleKey(User $user): string
    {
        return '2fa-challenge:'.$user->id.'|127.0.0.1';
    }

    private function emailOtpThrottleKey(User $user): string
    {
        return 'email-verify-otp:'.$user->id;
    }

    /**
     * Extract a named cookie value from an HTTP test response (including queued cookies).
     */
    private function cookieValueFromResponse($response, string $name): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            /** @var Cookie $cookie */
            if ($cookie->getName() === $name) {
                return $cookie->getValue();
            }
        }

        return null;
    }

    // =================================================================
    // MFA HTTP
    // =================================================================

    public function test_mfa_valid_totp_challenge_grants_protected_access(): void
    {
        $password = 'password123';
        $user = $this->makeActiveUser('mfa_totp_ok', $password);
        [$user] = $this->enableMfa($user);

        $this->post('/login', ['login' => 'mfa_totp_ok', 'password' => $password])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')
            ->assertRedirect(route('2fa.challenge'));

        $totp = $user->fresh()->makeTwoFactorCode();
        $this->post('/2fa/verify', ['code' => $totp])
            ->assertRedirect();

        $this->assertNotNull(session('auth.2fa_confirmed_at'));

        $allowed = $this->get('/dashboard');
        $this->assertFalse(
            $allowed->isRedirect() && str_contains((string) $allowed->headers->get('Location'), '2fa'),
            'Protected route must not re-challenge after successful MFA'
        );
    }

    public function test_mfa_invalid_totp_is_rejected(): void
    {
        $password = 'password123';
        $user = $this->makeActiveUser('mfa_totp_bad', $password);
        [$user] = $this->enableMfa($user);

        $this->post('/login', ['login' => 'mfa_totp_bad', 'password' => $password]);
        RateLimiter::clear($this->mfaThrottleKey($user));

        $this->from(route('2fa.challenge'))
            ->post('/2fa/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertNull(session('auth.2fa_confirmed_at'));
    }

    public function test_mfa_valid_recovery_code_and_reuse_rejected(): void
    {
        $password = 'password123';
        $user = $this->makeActiveUser('mfa_rec', $password);
        [$user, $codes] = $this->enableMfa($user);
        $this->assertNotEmpty($codes, 'Expected at least one recovery code');
        $recovery = (string) $codes->first();

        $this->post('/login', ['login' => 'mfa_rec', 'password' => $password]);
        RateLimiter::clear($this->mfaThrottleKey($user));

        $this->post('/2fa/verify', ['code' => $recovery])->assertRedirect();
        $this->assertNotNull(session('auth.2fa_confirmed_at'));

        $this->post('/logout');
        $this->post('/login', ['login' => 'mfa_rec', 'password' => $password]);
        RateLimiter::clear($this->mfaThrottleKey($user));
        session()->forget('auth.2fa_confirmed_at');

        $this->from(route('2fa.challenge'))
            ->post('/2fa/verify', ['code' => $recovery])
            ->assertSessionHasErrors('code');
        $this->assertNull(session('auth.2fa_confirmed_at'));
    }

    public function test_mfa_rate_limit_natural_expiry_and_success_clears(): void
    {
        $password = 'password123';
        $user = $this->makeActiveUser('mfa_lock', $password);
        [$user] = $this->enableMfa($user);

        $this->post('/login', ['login' => 'mfa_lock', 'password' => $password]);
        $key = $this->mfaThrottleKey($user);
        RateLimiter::clear($key);

        for ($i = 0; $i < 5; $i++) {
            $this->from(route('2fa.challenge'))
                ->post('/2fa/verify', ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $this->assertGreaterThan(0, RateLimiter::availableIn($key));
        $attemptsAtLock = RateLimiter::attempts($key);

        // Locked request must not increment.
        $this->from(route('2fa.challenge'))
            ->post('/2fa/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $lockMessage = session('errors')?->get('code')[0] ?? '';
        $this->assertStringContainsStringIgnoringCase('too many', $lockMessage);
        $this->assertSame($attemptsAtLock, RateLimiter::attempts($key));

        // Natural expiry: decay is 15 minutes — travel past it without clear().
        $this->travel(16)->minutes();

        $this->assertFalse(
            RateLimiter::tooManyAttempts($key, 5),
            'Lock must expire via time travel without RateLimiter::clear()'
        );

        $totp = $user->fresh()->makeTwoFactorCode();
        $this->post('/2fa/verify', ['code' => $totp])->assertRedirect();
        $this->assertNotNull(session('auth.2fa_confirmed_at'));
        $this->assertSame(0, RateLimiter::attempts($key), 'Successful verification must clear failure state');
    }

    public function test_mfa_safe_device_cookie_issued_and_respected(): void
    {
        config(['two-factor.safe_devices.enabled' => true]);
        $cookieName = (string) config('two-factor.safe_devices.cookie', '_2fa_remember');

        $password = 'password123';
        $user = $this->makeActiveUser('mfa_safe', $password);
        [$user] = $this->enableMfa($user);

        $this->post('/login', ['login' => 'mfa_safe', 'password' => $password]);
        RateLimiter::clear($this->mfaThrottleKey($user));

        $totp = $user->fresh()->makeTwoFactorCode();
        $verify = $this->post('/2fa/verify', ['code' => $totp, 'remember' => true]);
        $verify->assertRedirect();

        // Cookie must appear on the response (package queues the configured cookie name).
        $responseCookie = $this->cookieValueFromResponse($verify, $cookieName);
        $this->assertNotNull(
            $responseCookie,
            "Expected Set-Cookie for [{$cookieName}] after MFA verify with remember=true"
        );

        // Plain token is stored on the account's safe_devices collection (Laragear).
        $user->refresh();
        $this->assertNotEmpty($user->safeDevices(), 'safe_devices must be recorded after remember=true');
        $token = $user->safeDevices()->first()['2fa_remember'] ?? null;
        $this->assertNotEmpty($token, 'Expected 2fa_remember token on the account safe device');

        // Fresh login with the same cookie — MFA challenge must be bypassed.
        $this->post('/logout');
        $this->withUnencryptedCookie($cookieName, $token)
            ->post('/login', ['login' => 'mfa_safe', 'password' => $password])
            ->assertRedirect();

        session()->forget('auth.2fa_confirmed_at');

        $challenge = $this->withUnencryptedCookie($cookieName, $token)
            ->get(route('2fa.challenge'));
        $challenge->assertRedirect();
        $location = (string) $challenge->headers->get('Location');
        $this->assertStringNotContainsString('2fa/challenge', $location);
        $this->assertStringNotContainsString('login', $location);

        $dashboard = $this->withUnencryptedCookie($cookieName, $token)
            ->get('/dashboard');
        $this->assertFalse(
            $dashboard->isRedirect() && str_contains((string) $dashboard->headers->get('Location'), '2fa'),
            'Dashboard must not redirect to MFA when safe-device cookie is present and enabled'
        );

        // Same cookie must NOT bypass when safe devices are disabled.
        config(['two-factor.safe_devices.enabled' => false]);
        session()->forget('auth.2fa_confirmed_at');

        $this->withUnencryptedCookie($cookieName, $token)
            ->get('/dashboard')
            ->assertRedirect(route('2fa.challenge'));
    }

    // =================================================================
    // School administrator HTTP
    // =================================================================

    public function test_http_school_contact_email_alone_does_not_create_admin(): void
    {
        $this->actingAsSchoolCreator();
        $usersBefore = User::query()->count();
        $profilesBefore = Profile::query()->count();

        $response = $this->post(route('schools.store'), [
            'name' => 'Contact Only School',
            'email' => 'school-contact-only@example.com',
            'contact_name' => 'Reception',
        ]);

        $this->assertTrue(
            $response->isRedirect() || $response->status() === 201,
            'Expected successful school create, got '.$response->status()
        );
        $this->assertDatabaseHas('schools', [
            'name' => 'Contact Only School',
            'email' => 'school-contact-only@example.com',
        ]);
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($profilesBefore, Profile::query()->count());
        $school = School::query()->where('name', 'Contact Only School')->first();
        $this->assertNotNull($school);
        $this->assertSame(0, $school->users()->count());
    }

    public function test_http_explicit_admin_email_provisions_new_person_and_account(): void
    {
        $this->actingAsSchoolCreator();

        $preexisting = Profile::factory()->create([
            'email' => 'explicit-admin@example.com',
            'first_name' => 'Preexisting',
        ]);
        $this->assertNull($preexisting->user_id);

        $usersBefore = User::query()->count();
        $profilesBefore = Profile::query()->count();

        $response = $this->post(route('schools.store'), [
            'name' => 'Explicit Admin School',
            'email' => 'office@example.com',
            'admin_email' => 'explicit-admin@example.com',
            'admin_name' => 'Brand New',
            'admin_password' => 'password123',
        ]);

        $this->assertTrue(
            $response->isRedirect() || $response->status() === 201,
            'Expected successful school+admin create, got '.$response->status()
        );

        $school = School::query()->where('name', 'Explicit Admin School')->first();
        $this->assertNotNull($school);
        $this->assertSame('office@example.com', $school->email);

        // Independent Person with same email string (no identity match).
        $this->assertSame(
            2,
            Profile::query()->whereRaw('LOWER(email) = ?', ['explicit-admin@example.com'])->count()
        );
        $this->assertSame($profilesBefore + 1, Profile::query()->count());
        $this->assertSame($usersBefore + 1, User::query()->count());

        $newPerson = Profile::query()
            ->whereRaw('LOWER(email) = ?', ['explicit-admin@example.com'])
            ->where('first_name', 'Brand')
            ->whereNotNull('user_id')
            ->first();
        $this->assertNotNull($newPerson, 'New Person for admin must exist and be linked to an account');
        $this->assertNotSame($preexisting->id, $newPerson->id);

        $account = User::query()->find($newPerson->user_id);
        $this->assertNotNull($account);
        $this->assertTrue($account->isActiveLifecycle());
        $this->assertSame(AccountLifecycle::Active, $account->lifecycle);
        $this->assertTrue($account->schools()->where('schools.id', $school->id)->exists());
        $this->assertTrue(
            $account->hasRole('admin', $school->id),
            'Administrator role must be assigned at the School team scope'
        );
    }

    public function test_http_existing_account_admin_assignment(): void
    {
        $this->actingAsSchoolCreator();
        $existing = $this->makeActiveUser('existing_admin_http', email: 'existing-admin@example.com');
        $personId = $existing->profile->id;
        $accountId = $existing->id;
        $usersBefore = User::query()->count();
        $profilesBefore = Profile::query()->count();

        $response = $this->post(route('schools.store'), [
            'name' => 'Existing Admin School',
            'email' => 'office-exist@example.com',
            'account_id' => $existing->id,
        ]);

        $this->assertTrue(
            $response->isRedirect() || $response->status() === 201,
            'Expected successful school+existing admin, got '.$response->status()
        );

        $school = School::query()->where('name', 'Existing Admin School')->first();
        $this->assertNotNull($school);

        $existing->refresh();
        $this->assertSame($accountId, $existing->id);
        $this->assertSame($personId, $existing->profile->id);
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($profilesBefore, Profile::query()->count());
        $this->assertTrue($existing->schools()->where('schools.id', $school->id)->exists());
        $this->assertTrue(
            $existing->hasRole('admin', $school->id),
            'Administrator role must be assigned at the School team scope'
        );
    }

    public function test_http_failed_admin_assignment_rolls_back_school(): void
    {
        $this->actingAsSchoolCreator();
        $schoolsBefore = School::query()->count();
        $usersBefore = User::query()->count();

        $response = $this->post(route('schools.store'), [
            'name' => 'Rollback School',
            'email' => 'rollback-office@example.com',
            'account_id' => (string) Str::uuid(),
        ]);

        $this->assertTrue(
            in_array($response->status(), [302, 422, 500], true),
            'Expected failure status, got '.$response->status()
        );
        // Redirect-back with error is acceptable; school must not exist.
        $this->assertSame($schoolsBefore, School::query()->count());
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertDatabaseMissing('schools', ['name' => 'Rollback School']);
    }

    public function test_http_unauthorized_school_create_makes_neither_school_nor_account(): void
    {
        $intruder = $this->makeActiveUser('intruder');
        $this->actingAs($intruder);
        $schoolsBefore = School::query()->count();
        $usersBefore = User::query()->count();

        $response = $this->post(route('schools.store'), [
            'name' => 'Unauthorized School',
            'email' => 'nope@example.com',
            'admin_email' => 'nope-admin@example.com',
            'admin_name' => 'Nope',
            'admin_password' => 'password123',
        ]);

        $response->assertForbidden();
        $this->assertSame($schoolsBefore, School::query()->count());
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertDatabaseMissing('schools', ['name' => 'Unauthorized School']);
    }

    // =================================================================
    // Email OTP HTTP
    // =================================================================

    public function test_email_otp_http_invalid_increments_lock_natural_expiry_and_success(): void
    {
        $user = $this->makeActiveUser('otp_http', email: 'otp-http@example.com');
        $this->actingAs($user);
        $key = $this->emailOtpThrottleKey($user);
        RateLimiter::clear($key);

        $first = $this->postJson('/verify-email', ['otp' => '000000']);
        $first->assertStatus(422);
        $this->assertStringNotContainsString('too many', strtolower($first->getContent()));
        $this->assertSame(1, RateLimiter::attempts($key));

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/verify-email', ['otp' => '000000'])->assertStatus(422);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $this->assertGreaterThan(0, RateLimiter::availableIn($key));
        $attemptsAtLock = RateLimiter::attempts($key);

        $locked = $this->postJson('/verify-email', ['otp' => '000000']);
        $locked->assertStatus(422);
        $this->assertStringContainsString('too many', strtolower($locked->getContent()));
        $this->assertSame($attemptsAtLock, RateLimiter::attempts($key));

        // Natural expiry — 15 minute decay; no clear().
        $this->travel(16)->minutes();
        $this->assertFalse(
            RateLimiter::tooManyAttempts($key, 5),
            'Email OTP lock must expire via time travel without RateLimiter::clear()'
        );

        $otp = app(Otp::class)->generate(strtolower('otp-http@example.com'), 'numeric', 6, 10);
        $token = $otp->token ?? $otp->otp ?? null;
        $this->assertNotEmpty($token, 'OTP package did not return a token');

        // Prior residual attempts may remain below threshold after decay; success must clear.
        $ok = $this->postJson('/verify-email', ['otp' => (string) $token]);
        $ok->assertSuccessful();
        $user->refresh();
        $this->assertNotNull($user->auth_email_verified_at);
        $this->assertFalse((bool) $user->auth_email_enabled);
        $this->assertFalse($user->canUseEmailForLogin());
        $this->assertSame(0, RateLimiter::attempts($key), 'Successful verification must clear OTP failures');
    }

    public function test_email_otp_expired_rejected(): void
    {
        $user = $this->makeActiveUser('otp_exp', email: 'otp-exp@example.com');
        $this->actingAs($user);
        RateLimiter::clear($this->emailOtpThrottleKey($user));

        $otp = app(Otp::class)->generate(strtolower('otp-exp@example.com'), 'numeric', 6, 1);
        $token = $otp->token ?? $otp->otp ?? null;
        $this->assertNotEmpty($token);

        $this->travel(5)->minutes();

        $this->postJson('/verify-email', ['otp' => (string) $token])->assertStatus(422);
        $user->refresh();
        $this->assertNull($user->auth_email_verified_at);
    }
}
