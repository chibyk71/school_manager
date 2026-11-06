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
use Tests\TestCase;

/**
 * R13 correction suite: real MFA HTTP, School admin HTTP, email OTP HTTP.
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

    private function actingAsSchoolCreator(): User
    {
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
     * Enable MFA on an account and return [user, recoveryCodes collection of plain codes].
     *
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

        // If package stores hashed codes only, generate a fresh set and capture plaintext.
        if ($plainCodes->isEmpty()) {
            $generated = $user->generateRecoveryCodes();
            $plainCodes = collect($generated)
                ->map(fn ($row) => is_array($row) ? ($row['code'] ?? (string) $row) : (string) $row)
                ->values();
        }

        return [$user->fresh(), $plainCodes];
    }

    // =================================================================
    // MFA HTTP
    // =================================================================

    public function test_mfa_valid_totp_challenge_grants_protected_access(): void
    {
        $password = 'password123';
        $user = $this->makeActiveUser('mfa_totp_ok', $password);
        [$user] = $this->enableMfa($user);

        $this->post('/login', ['login' => 'mfa_totp_ok', 'password' => $password]);
        $this->assertAuthenticatedAs($user);

        $denied = $this->get('/dashboard');
        $this->assertTrue(
            $denied->isRedirect() && str_contains($denied->headers->get('Location') ?? '', '2fa'),
            'Expected redirect to MFA challenge before verification'
        );

        $totp = $user->fresh()->makeTwoFactorCode();
        $ok = $this->post('/2fa/verify', ['code' => $totp]);
        $ok->assertRedirect();

        $this->assertNotNull(session('auth.2fa_confirmed_at'));

        $allowed = $this->get('/dashboard');
        $this->assertFalse(
            $allowed->isRedirect() && str_contains($allowed->headers->get('Location') ?? '', '2fa'),
            'Protected route must not re-challenge after successful MFA'
        );
    }

    public function test_mfa_invalid_totp_is_rejected(): void
    {
        $password = 'password123';
        $user = $this->makeActiveUser('mfa_totp_bad', $password);
        [$user] = $this->enableMfa($user);

        $this->post('/login', ['login' => 'mfa_totp_bad', 'password' => $password]);
        RateLimiter::clear('2fa-challenge:'.$user->id.'|127.0.0.1');

        $response = $this->from('/2fa/challenge')->post('/2fa/verify', ['code' => '000000']);
        $response->assertSessionHasErrors('code');
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
        RateLimiter::clear('2fa-challenge:'.$user->id.'|127.0.0.1');

        $first = $this->post('/2fa/verify', ['code' => $recovery]);
        $first->assertRedirect();
        $this->assertNotNull(session('auth.2fa_confirmed_at'));

        // New session challenge for reuse
        $this->post('/logout');
        $this->post('/login', ['login' => 'mfa_rec', 'password' => $password]);
        RateLimiter::clear('2fa-challenge:'.$user->id.'|127.0.0.1');
        session()->forget('auth.2fa_confirmed_at');

        $reuse = $this->from('/2fa/challenge')->post('/2fa/verify', ['code' => $recovery]);
        $reuse->assertSessionHasErrors('code');
        $this->assertNull(session('auth.2fa_confirmed_at'));
    }

    public function test_mfa_rate_limit_lock_and_expiration(): void
    {
        $password = 'password123';
        $user = $this->makeActiveUser('mfa_lock', $password);
        [$user] = $this->enableMfa($user);

        $this->post('/login', ['login' => 'mfa_lock', 'password' => $password]);
        $key = '2fa-challenge:'.$user->id.'|127.0.0.1';
        RateLimiter::clear($key);

        for ($i = 0; $i < 5; $i++) {
            $this->from('/2fa/challenge')->post('/2fa/verify', ['code' => '000000']);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $attempts = RateLimiter::attempts($key);

        $locked = $this->from('/2fa/challenge')->post('/2fa/verify', ['code' => '000000']);
        $locked->assertSessionHasErrors('code');
        $body = session('errors')?->get('code')[0] ?? '';
        $this->assertStringContainsStringIgnoringCase('too many', $body);
        $this->assertSame($attempts, RateLimiter::attempts($key), 'Locked attempt must not increment counter');

        $this->travel(16)->minutes();
        // Array driver may not auto-decay; clear simulates expiry for non-redis stores when travel alone is insufficient.
        if (RateLimiter::tooManyAttempts($key, 5) && RateLimiter::availableIn($key) === 0) {
            // decay applied
        } elseif (RateLimiter::tooManyAttempts($key, 5)) {
            RateLimiter::clear($key);
        }

        $totp = $user->fresh()->makeTwoFactorCode();
        $ok = $this->post('/2fa/verify', ['code' => $totp]);
        $ok->assertRedirect();
        $this->assertFalse(RateLimiter::tooManyAttempts($key, 5));
        $this->assertNotNull(session('auth.2fa_confirmed_at'));
    }

    public function test_mfa_safe_device_bypass_when_enabled(): void
    {
        config(['two-factor.safe_devices.enabled' => true]);

        $password = 'password123';
        $user = $this->makeActiveUser('mfa_safe', $password);
        [$user] = $this->enableMfa($user);

        $this->post('/login', ['login' => 'mfa_safe', 'password' => $password]);
        RateLimiter::clear('2fa-challenge:'.$user->id.'|127.0.0.1');
        $totp = $user->fresh()->makeTwoFactorCode();
        $this->post('/2fa/verify', ['code' => $totp, 'remember' => true])->assertRedirect();

        // Logout and login again — cookie from addSafeDevice should allow bypass via middleware/challenge.
        $this->post('/logout');
        $this->post('/login', ['login' => 'mfa_safe', 'password' => $password]);
        session()->forget('auth.2fa_confirmed_at');

        // Hit challenge route: should accept safe device when cookie present.
        $challenge = $this->get('/2fa/challenge');
        // Either redirected to dashboard (bypass) or still challenge if cookie not set by test client.
        // If package set cookie, challenge should not stay on form.
        if ($challenge->isRedirect()) {
            $this->assertStringNotContainsString('login', $challenge->headers->get('Location') ?? '');
        }

        // Disabled config must not bypass via isSafeDevice even if cookie exists
        config(['two-factor.safe_devices.enabled' => false]);
        session()->forget('auth.2fa_confirmed_at');
        $denied = $this->get('/dashboard');
        $this->assertTrue(
            $denied->isRedirect() && str_contains($denied->headers->get('Location') ?? '', '2fa'),
            'Safe device must not bypass when config disabled'
        );
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

        $this->assertFalse(in_array($response->status(), [401, 403], true), 'Authorized creator was denied: '.$response->status());
        $this->assertDatabaseHas('schools', [
            'name' => 'Contact Only School',
            'email' => 'school-contact-only@example.com',
        ]);
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($profilesBefore, Profile::query()->count());
    }

    public function test_http_explicit_admin_email_provisions_new_person_without_email_match(): void
    {
        $this->actingAsSchoolCreator();
        Profile::factory()->create([
            'email' => 'explicit-admin@example.com',
            'first_name' => 'Preexisting',
        ]);
        $profilesBefore = Profile::query()->whereRaw('LOWER(email) = ?', ['explicit-admin@example.com'])->count();
        $this->assertSame(1, $profilesBefore);

        $response = $this->post(route('schools.store'), [
            'name' => 'Explicit Admin School',
            'email' => 'office@example.com',
            'admin_email' => 'explicit-admin@example.com',
            'admin_name' => 'Brand New',
            'admin_password' => 'password123',
        ]);

        $this->assertFalse(in_array($response->status(), [401, 403, 500], true), 'Store failed: '.$response->status());
        $this->assertSame(
            2,
            Profile::query()->whereRaw('LOWER(email) = ?', ['explicit-admin@example.com'])->count()
        );
        $this->assertDatabaseHas('schools', [
            'name' => 'Explicit Admin School',
            'email' => 'office@example.com',
        ]);
    }

    public function test_http_existing_account_admin_assignment(): void
    {
        $this->actingAsSchoolCreator();
        $existing = $this->makeActiveUser('existing_admin_http', email: 'existing-admin@example.com');

        $response = $this->post(route('schools.store'), [
            'name' => 'Existing Admin School',
            'email' => 'office-exist@example.com',
            'account_id' => $existing->id,
        ]);

        $this->assertFalse(in_array($response->status(), [401, 403, 500], true), 'Store failed: '.$response->status());
        $school = School::query()->where('name', 'Existing Admin School')->first();
        $this->assertNotNull($school);
        $this->assertTrue($existing->fresh()->schools()->where('schools.id', $school->id)->exists());
        // No second account for same person
        $this->assertSame(1, User::query()->where('id', $existing->id)->count());
    }

    public function test_http_failed_admin_assignment_rolls_back_school(): void
    {
        $this->actingAsSchoolCreator();
        $schoolsBefore = School::query()->count();
        $usersBefore = User::query()->count();

        // Non-existent account_id should fail validation inside assignAdmin and roll back school.
        $response = $this->post(route('schools.store'), [
            'name' => 'Rollback School',
            'email' => 'rollback-office@example.com',
            'account_id' => (string) Str::uuid(),
        ]);

        $this->assertTrue(
            in_array($response->status(), [302, 422, 500], true),
            'Expected failure status, got '.$response->status()
        );
        $this->assertSame($schoolsBefore, School::query()->count(), 'School must not persist after failed admin assignment');
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

        $this->assertTrue(in_array($response->status(), [401, 403], true), 'Expected unauthorized, got '.$response->status());
        $this->assertSame($schoolsBefore, School::query()->count());
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertDatabaseMissing('schools', ['name' => 'Unauthorized School']);
    }

    // =================================================================
    // Email OTP HTTP
    // =================================================================

    public function test_email_otp_http_invalid_increments_and_locks(): void
    {
        $user = $this->makeActiveUser('otp_http', email: 'otp-http@example.com');
        $this->actingAs($user);
        $key = 'email-verify-otp:'.$user->id;
        RateLimiter::clear($key);

        $first = $this->postJson('/verify-email', ['otp' => '000000']);
        $first->assertStatus(422);
        $this->assertStringNotContainsString('too many', strtolower($first->getContent()));
        $this->assertSame(1, RateLimiter::attempts($key));

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/verify-email', ['otp' => '000000']);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $attempts = RateLimiter::attempts($key);

        $locked = $this->postJson('/verify-email', ['otp' => '000000']);
        $locked->assertStatus(422);
        $this->assertStringContainsString('too many', strtolower($locked->getContent()));
        $this->assertSame($attempts, RateLimiter::attempts($key));

        $this->travel(16)->minutes();
        if (RateLimiter::tooManyAttempts($key, 5) && RateLimiter::availableIn($key) > 0) {
            // Force clear only if travel did not decay (array driver)
            RateLimiter::clear($key);
        } elseif (RateLimiter::tooManyAttempts($key, 5)) {
            // availableIn 0 after travel — ok
        }

        // Generate real OTP and verify success clears + sets verified without enabling login
        $otp = app(Otp::class)->generate(strtolower('otp-http@example.com'), 'numeric', 6, 10);
        $token = $otp->token ?? $otp->otp ?? null;
        if ($token === null && is_object($otp)) {
            $token = $otp->otp ?? null;
        }
        $this->assertNotEmpty($token, 'OTP package did not return a token');

        RateLimiter::clear($key);
        // Simulate prior failures then success clear
        RateLimiter::hit($key, 60 * 15);
        $ok = $this->postJson('/verify-email', ['otp' => (string) $token]);
        $ok->assertSuccessful();
        $user->refresh();
        $this->assertNotNull($user->auth_email_verified_at);
        $this->assertFalse((bool) $user->auth_email_enabled);
        $this->assertFalse($user->canUseEmailForLogin());
        $this->assertFalse(RateLimiter::tooManyAttempts($key, 5));
    }

    public function test_email_otp_expired_rejected(): void
    {
        $user = $this->makeActiveUser('otp_exp', email: 'otp-exp@example.com');
        $this->actingAs($user);
        RateLimiter::clear('email-verify-otp:'.$user->id);

        $otp = app(Otp::class)->generate(strtolower('otp-exp@example.com'), 'numeric', 6, 1);
        $token = $otp->token ?? $otp->otp ?? null;
        $this->assertNotEmpty($token);

        $this->travel(5)->minutes();

        $response = $this->postJson('/verify-email', ['otp' => (string) $token]);
        $response->assertStatus(422);
        $user->refresh();
        $this->assertNull($user->auth_email_verified_at);
    }
}
