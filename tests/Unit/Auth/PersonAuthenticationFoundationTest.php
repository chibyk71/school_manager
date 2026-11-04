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
}
