<?php

namespace Tests\Unit\Auth;

use App\Enums\Auth\AccountLifecycle;
use App\Models\Profile;
use App\Models\User;
use App\Services\UserManagement\UserAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonAuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    private UserAccountService $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = app(UserAccountService::class);
    }

    public function test_person_can_exist_without_authentication_account(): void
    {
        $person = Profile::factory()->create(['email' => 'a@example.com']);

        $this->assertNull($person->user_id);
        $this->assertNull($person->user);
    }

    public function test_person_can_have_one_authentication_account(): void
    {
        $person = Profile::factory()->create();
        $account = $this->accounts->createForPerson($person, [
            'username' => 'jane_doe',
            'password' => 'password123',
        ], ['lifecycle' => 'active']);

        $this->assertTrue($account->isActiveLifecycle());
        $this->assertEquals($account->id, $person->fresh()->user_id);
        $this->assertEquals($person->id, $account->profile->id);
    }

    public function test_person_cannot_receive_second_account(): void
    {
        $person = Profile::factory()->create();
        $this->accounts->createForPerson($person, [
            'username' => 'one',
            'password' => 'password123',
        ], ['lifecycle' => 'active']);

        $this->expectException(ValidationException::class);
        $this->accounts->createForPerson($person->fresh(), [
            'username' => 'two',
            'password' => 'password123',
        ], ['lifecycle' => 'active']);
    }

    public function test_soft_deleted_person_cannot_receive_account(): void
    {
        $person = Profile::factory()->create();
        $person->delete();

        $this->expectException(ValidationException::class);
        $this->accounts->createForPerson($person, [
            'username' => 'gone',
            'password' => 'password123',
        ], ['lifecycle' => 'active']);
    }

    public function test_soft_deleted_person_blocks_authentication(): void
    {
        $person = Profile::factory()->create(['email' => 'x@example.com']);
        $account = $this->accounts->createForPerson($person, [
            'username' => 'blocked',
            'password' => 'password123',
        ], ['lifecycle' => 'active']);

        $person->delete();
        $account->refresh();

        $this->assertFalse($account->canAuthenticate());
    }

    public function test_restore_person_restores_eligibility(): void
    {
        $person = Profile::factory()->create();
        $account = $this->accounts->createForPerson($person, [
            'username' => 'restored',
            'password' => 'password123',
        ], ['lifecycle' => 'active']);

        $person->delete();
        $this->assertFalse($account->fresh()->canAuthenticate());

        $person->restore();
        $this->assertTrue($account->fresh()->canAuthenticate());
    }

    public function test_duplicate_person_emails_are_allowed(): void
    {
        Profile::factory()->create(['email' => 'shared@example.com']);
        Profile::factory()->create(['email' => 'shared@example.com']);

        $this->assertEquals(2, Profile::where('email', 'shared@example.com')->count());
    }

    public function test_provisioned_account_cannot_authenticate(): void
    {
        $person = Profile::factory()->create();
        $account = $this->accounts->createForPerson($person, [
            'username' => 'prov',
        ], ['lifecycle' => 'provisioned']);

        $this->assertTrue($account->isProvisioned());
        $this->assertNull($account->password);
        $this->assertFalse($account->canAuthenticate());
    }

    public function test_active_creation_requires_password(): void
    {
        $person = Profile::factory()->create();

        $this->expectException(ValidationException::class);
        $this->accounts->createForPerson($person, [
            'username' => 'nopass',
        ], ['lifecycle' => 'active']);
    }

    public function test_activate_transitions_provisioned_to_active(): void
    {
        $person = Profile::factory()->create();
        $account = $this->accounts->createForPerson($person, [
            'username' => 'to_activate',
        ], ['lifecycle' => 'provisioned']);

        $activated = $this->accounts->activate($account, 'password123');
        $this->assertTrue($activated->isActiveLifecycle());
        $this->assertTrue(Hash::check('password123', $activated->password));
    }

    public function test_email_login_requires_verified_and_enabled(): void
    {
        $person = Profile::factory()->create(['email' => 'login@example.com']);
        $account = $this->accounts->createForPerson($person, [
            'username' => 'emaillogin',
            'password' => 'password123',
        ], ['lifecycle' => 'active']);

        $this->assertFalse($account->canUseEmailForLogin());

        $this->accounts->markAuthEmailVerified($account);
        $this->assertFalse($account->fresh()->canUseEmailForLogin());

        $this->accounts->setAuthEmailEnabled($account->fresh(), true);
        $this->assertTrue($account->fresh()->canUseEmailForLogin());
    }

    public function test_person_email_change_resets_auth_email_state(): void
    {
        $person = Profile::factory()->create(['email' => 'old@example.com']);
        $account = $this->accounts->createForPerson($person, [
            'username' => 'chmail',
            'password' => 'password123',
        ], ['lifecycle' => 'active']);
        $this->accounts->markAuthEmailVerified($account);
        $this->accounts->setAuthEmailEnabled($account->fresh(), true);

        $person->update(['email' => 'new@example.com']);
        $account->refresh();

        $this->assertNull($account->auth_email_verified_at);
        $this->assertFalse($account->auth_email_enabled);
    }

    public function test_users_table_has_no_auth_email_string_column(): void
    {
        $this->assertFalse(\Schema::hasColumn('users', 'auth_email'));
        $this->assertFalse(\Schema::hasColumn('users', 'email'));
        $this->assertFalse(\Schema::hasColumn('users', 'is_active'));
        $this->assertFalse(\Schema::hasColumn('users', 'must_change_password'));
        $this->assertTrue(\Schema::hasColumn('users', 'lifecycle'));
        $this->assertTrue(\Schema::hasColumn('users', 'force_password_change'));
        $this->assertTrue(\Schema::hasColumn('users', 'auth_email_verified_at'));
        $this->assertTrue(\Schema::hasColumn('users', 'auth_email_enabled'));
    }

    public function test_no_role_required_for_authentication_eligibility(): void
    {
        $person = Profile::factory()->create();
        $account = $this->accounts->createForPerson($person, [
            'username' => 'norole',
            'password' => 'password123',
        ], ['lifecycle' => 'active']);

        $this->assertTrue($account->canAuthenticate());
        $this->assertTrue($account->schools()->count() === 0);
    }

    public function test_force_password_change_keeps_lifecycle_active(): void
    {
        $person = Profile::factory()->create();
        $account = $this->accounts->createForPerson($person, [
            'username' => 'forcepw',
            'password' => 'password123',
        ], [
            'lifecycle' => 'active',
            'force_password_change' => true,
        ]);

        $this->assertTrue($account->isActiveLifecycle());
        $this->assertTrue($account->requiresPasswordChange());
        $this->assertTrue($account->canAuthenticate());
    }

    public function test_auth_phone_independent_of_person_phone(): void
    {
        $person = Profile::factory()->create(['phone' => '+1111111111']);
        $account = $this->accounts->createForPerson($person, [
            'username' => 'phoneid',
            'password' => 'password123',
            'auth_phone' => '+2222222222',
        ], ['lifecycle' => 'active']);

        $this->assertEquals('+2222222222', $account->auth_phone);
        $this->assertNotEquals($person->phone, $account->auth_phone);
        $this->assertFalse($account->canUsePhoneForLogin());
    }

    public function test_profile_user_relationship_both_directions(): void
    {
        $person = Profile::factory()->create();
        $account = $this->accounts->createForPerson($person, [
            'username' => 'rel_user',
            'password' => 'password123',
        ], ['lifecycle' => 'active']);

        $this->assertInstanceOf(User::class, $person->fresh()->user);
        $this->assertInstanceOf(Profile::class, $account->fresh()->profile);
        $this->assertEquals($person->id, $account->profile->id);
        $this->assertEquals($account->id, $person->user->id);
    }

    public function test_shared_email_login_fails_closed_when_multiple_eligible(): void
    {
        $p1 = Profile::factory()->create(['email' => 'shared@example.com']);
        $p2 = Profile::factory()->create(['email' => 'shared@example.com']);
        $a1 = $this->accounts->createForPerson($p1, ['username' => 's1', 'password' => 'password123'], ['lifecycle' => 'active']);
        $a2 = $this->accounts->createForPerson($p2, ['username' => 's2', 'password' => 'password123'], ['lifecycle' => 'active']);
        $this->accounts->markAuthEmailVerified($a1);
        $this->accounts->setAuthEmailEnabled($a1->fresh(), true);
        $this->accounts->markAuthEmailVerified($a2);
        $this->accounts->setAuthEmailEnabled($a2->fresh(), true);

        $eligible = User::query()
            ->whereHas('profile', fn ($q) => $q->whereRaw('LOWER(email) = ?', ['shared@example.com']))
            ->whereNotNull('auth_email_verified_at')
            ->where('auth_email_enabled', true)
            ->count();

        $this->assertGreaterThan(1, $eligible);
    }

    public function test_account_created_event_fires_even_without_notification(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\Auth\AuthenticationAccountCreated::class]);

        $person = Profile::factory()->create();
        $this->accounts->createForPerson($person, [
            'username' => 'evt',
            'password' => 'password123',
        ], [
            'lifecycle' => 'active',
            'send_notification' => false,
        ]);

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\Auth\AuthenticationAccountCreated::class);
    }

    public function test_password_recovery_resolution_requires_unique_linked_person(): void
    {
        $shared = 'dup-reset@example.com';
        $p1 = Profile::factory()->create(['email' => $shared]);
        $p2 = Profile::factory()->create(['email' => $shared]);
        $this->accounts->createForPerson($p1, ['username' => 'r1', 'password' => 'password123'], ['lifecycle' => 'active']);
        $this->accounts->createForPerson($p2, ['username' => 'r2', 'password' => 'password123'], ['lifecycle' => 'active']);

        $matches = Profile::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($shared)])
            ->whereNotNull('user_id')
            ->whereNull('deleted_at')
            ->count();

        $this->assertGreaterThan(1, $matches);
    }

    public function test_admin_reset_password_does_not_activate_provisioned_account(): void
    {
        $person = Profile::factory()->create();
        $account = $this->accounts->createForPerson($person, [
            'username' => 'prov_reset',
            'password' => null,
        ], ['lifecycle' => 'provisioned']);

        $this->assertTrue($account->isProvisioned());
        $this->accounts->resetPassword($account, 'newpassword123');
        $account->refresh();
        $this->assertTrue($account->isProvisioned());
        $this->assertTrue($account->requiresPasswordChange());
    }

    public function test_person_service_creates_independent_person_without_account(): void
    {
        $service = app(\App\Services\UserManagement\PersonService::class);
        $person = $service->create([
            'first_name' => 'Independent',
            'last_name' => 'Person',
            'email' => 'independent@example.com',
        ]);

        $this->assertInstanceOf(Profile::class, $person);
        $this->assertNull($person->user_id);
        $this->assertDatabaseMissing('users', ['id' => $person->user_id]);
    }

    public function test_authentication_account_created_event_has_no_plain_password_property(): void
    {
        $ref = new \ReflectionClass(\App\Events\Auth\AuthenticationAccountCreated::class);
        $params = $ref->getConstructor()->getParameters();
        $names = array_map(fn ($p) => $p->getName(), $params);
        $this->assertNotContains('plainPassword', $names);
    }

    public function test_mark_auth_phone_verified_is_blocked_without_challenge_workflow(): void
    {
        $person = Profile::factory()->create();
        $account = $this->accounts->createForPerson($person, [
            'username' => 'nophone',
            'password' => 'password123',
            'auth_phone' => '+19998887777',
        ], ['lifecycle' => 'active']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->accounts->markAuthPhoneVerified($account);
    }
}
