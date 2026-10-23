<?php

namespace Tests\Feature\School;

use App\Jobs\SetupSchoolOnboarding;
use App\Models\Permission;
use App\Models\School;
use App\Models\User;
use App\Services\School\SchoolLifecycleService;
use App\States\School\Draft;
use App\States\School\Ready;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('schools')) {
            $this->markTestSkipped('schools table required');
        }
    }

    private function actingAsAuthorized(): User
    {
        $user = User::factory()->create();
        Gate::before(fn ($u, $ability) => true);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Real Laratrust-style membership + permission_user grants (same pattern as File Manager tests).
     */
    private function attachSchoolMember(User $user, School $school, array $permissions): void
    {
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['display_name' => $name]
            );
            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'user_type' => User::class,
                'school_id' => $school->id,
            ]);
            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'user_type' => User::class,
                'school_id' => null,
            ]);
        }

        DB::table('school_users')->insertOrIgnore([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        $user->unsetRelation('schools');
        $user->load('schools');
    }

    public function test_name_only_create_yields_draft_without_is_active_true(): void
    {
        $this->actingAsAuthorized();

        $response = $this->post(route('schools.store'), [
            'name' => 'Name Only Draft School',
        ]);

        // Form posts redirect with flash (Inertia/web); not a bare 2xx JSON body.
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $school = School::query()->where('name', 'Name Only Draft School')->first();
        $this->assertNotNull($school);
        $this->assertInstanceOf(Draft::class, $school->state);
        $this->assertFalse((bool) $school->is_active);
    }

    public function test_draft_create_does_not_dispatch_operational_onboarding_job(): void
    {
        Queue::fake();
        $this->actingAsAuthorized();

        $this->post(route('schools.store'), [
            'name' => 'Draft No Onboarding',
        ])->assertRedirect();

        Queue::assertNotPushed(SetupSchoolOnboarding::class);
    }

    public function test_store_rejects_prohibited_lifecycle_fields(): void
    {
        $this->actingAsAuthorized();

        $response = $this->from('/schools')->post(route('schools.store'), [
            'name' => 'Prohibited Fields School',
            'is_active' => true,
            'state' => 'active',
        ]);

        $response->assertSessionHasErrors(['is_active', 'state']);
        $this->assertNull(
            School::query()->where('name', 'Prohibited Fields School')->first()
        );
    }

    public function test_draft_can_be_updated_while_incomplete(): void
    {
        $this->actingAsAuthorized();
        $school = app(SchoolLifecycleService::class)->createDraft([
            'name' => 'Incomplete Draft',
        ]);

        $response = $this->put(route('schools.update', $school), [
            'name' => 'Incomplete Draft Updated',
            'code' => null,
            'email' => null,
            'type' => null,
            'contact_name' => null,
        ]);

        $response->assertRedirect();
        $school->refresh();
        $this->assertSame('Incomplete Draft Updated', $school->name);
        $this->assertInstanceOf(Draft::class, $school->state);
        $this->assertNull($school->code);
    }

    public function test_update_rejects_client_assigned_state_and_is_active(): void
    {
        $this->actingAsAuthorized();
        $school = app(SchoolLifecycleService::class)->createDraft([
            'name' => 'State Guard School',
        ]);

        $response = $this->from('/schools/'.$school->id)->put(route('schools.update', $school), [
            'name' => 'State Guard School',
            'state' => 'active',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['state', 'is_active']);
        $school->refresh();
        $this->assertInstanceOf(Draft::class, $school->state);
        $this->assertFalse((bool) $school->is_active);
    }

    public function test_inertia_mark_ready_on_incomplete_draft_redirects_with_validation_errors(): void
    {
        $this->actingAsAuthorized();
        $school = app(SchoolLifecycleService::class)->createDraft([
            'name' => 'Incomplete Inertia Draft',
        ]);

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->from('/schools/'.$school->id)->post(route('schools.mark-ready', $school));

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertInstanceOf(Draft::class, $school->fresh()->state);
    }

    public function test_inertia_successful_lifecycle_action_redirects_with_success_flash(): void
    {
        $this->actingAsAuthorized();
        $lifecycle = app(SchoolLifecycleService::class);
        $school = $lifecycle->createDraft(['name' => 'Archive Candidate']);

        // Archive requires Active|Suspended; suspend path needs Active. Use service to archive from Active.
        $school->state = \App\States\School\Active::class;
        $school->is_active = true;
        $school->save();

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->from('/schools/'.$school->id)->post(route('schools.archive', $school));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertInstanceOf(\App\States\School\Archived::class, $school->fresh()->state);
    }

    public function test_json_lifecycle_action_returns_json_without_inertia_header(): void
    {
        $this->actingAsAuthorized();
        $school = app(SchoolLifecycleService::class)->createDraft(['name' => 'JSON Lifecycle']);
        $school->state = \App\States\School\Active::class;
        $school->is_active = true;
        $school->save();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson(route('schools.archive', $school));

        $response->assertOk();
        $response->assertJsonStructure(['message', 'school']);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
    }

    public function test_unauthorized_user_cannot_soft_delete_school_returns_403(): void
    {
        $authorized = User::factory()->create();
        Gate::before(fn ($u) => $u->is($authorized) ? true : null);

        $this->actingAs($authorized);
        $school = app(SchoolLifecycleService::class)->createDraft(['name' => 'Protected School']);

        Gate::before(fn () => false);
        $this->actingAs(User::factory()->create());

        $response = $this->delete(route('schools.destroy'), ['ids' => [$school->id]]);

        $response->assertForbidden();
        $this->assertFalse($school->fresh()->trashed());
    }

    public function test_policy_boundary_member_can_update_outsider_cannot(): void
    {
        // Exercise SchoolPolicy without Gate::before short-circuit.
        // update() is membership-gated and does not require users()->count() === 0
        // (delete does, which conflicts with attaching the member on school_users).
        Gate::before(fn () => null);

        if (! Schema::hasTable('permission_user') || ! Schema::hasTable('school_users')) {
            $this->markTestSkipped('permission_user/school_users required for Laratrust membership');
        }

        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $school = app(SchoolLifecycleService::class)->createDraft(['name' => 'Membership School']);

        $this->attachSchoolMember($member, $school, ['school.update', 'school.view']);

        $this->assertTrue(
            $member->fresh()->schools->contains($school),
            'Member fixture must establish school_users membership'
        );

        $this->actingAs($member->fresh());
        $memberResponse = $this->from('/schools/'.$school->id)->put(route('schools.update', $school), [
            'name' => 'Membership School Updated',
        ]);
        $memberResponse->assertRedirect();
        $memberResponse->assertSessionHas('success');
        $this->assertSame('Membership School Updated', $school->fresh()->name);

        $this->actingAs($outsider);
        $outsiderResponse = $this->put(route('schools.update', $school), [
            'name' => 'Hijacked Name',
        ]);
        $outsiderResponse->assertForbidden();
        $this->assertSame('Membership School Updated', $school->fresh()->name);
    }

    public function test_policy_boundary_outsider_cannot_delete_empty_school(): void
    {
        Gate::before(fn () => null);

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_user')) {
            $this->markTestSkipped('roles/role_user required for super-admin fixture');
        }

        $admin = User::factory()->create();
        $outsider = User::factory()->create();
        $school = app(SchoolLifecycleService::class)->createDraft(['name' => 'Deletable Empty School']);

        // Super-admin path on SchoolPolicy::delete (still requires users()->count() === 0).
        $role = \App\Models\Role::query()->firstOrCreate(
            ['name' => 'super-admin'],
            ['display_name' => 'Super Admin']
        );
        DB::table('role_user')->insertOrIgnore([
            'role_id' => $role->id,
            'user_id' => $admin->id,
            'user_type' => User::class,
            'school_id' => null,
        ]);

        $this->assertSame(0, $school->users()->count());

        $this->actingAs($admin->fresh());
        $adminResponse = $this->delete(route('schools.destroy'), ['ids' => [$school->id]]);
        $this->assertTrue(in_array($adminResponse->status(), [200, 204, 302], true), 'expected success or redirect, got '.$adminResponse->status());
        $this->assertTrue($school->fresh()->trashed());

        // Second school for outsider denial (first is already trashed).
        $school2 = app(SchoolLifecycleService::class)->createDraft(['name' => 'Outsider Target']);
        $this->actingAs($outsider);
        $outsiderResponse = $this->delete(route('schools.destroy'), ['ids' => [$school2->id]]);
        $outsiderResponse->assertForbidden();
        $this->assertFalse($school2->fresh()->trashed());
    }

    public function test_bulk_toggle_status_is_gone(): void
    {
        $this->actingAsAuthorized();

        $response = $this->post(route('schools.bulk-toggle'), [
            'ids' => ['00000000-0000-0000-0000-000000000001'],
            'is_active' => true,
        ]);

        $response->assertStatus(410);
    }

    public function test_mark_ready_fails_when_readiness_incomplete(): void
    {
        $this->actingAsAuthorized();
        $school = app(SchoolLifecycleService::class)->createDraft([
            'name' => 'Not Ready School',
        ]);

        $response = $this->post(route('schools.mark-ready', $school));

        $response->assertStatus(422);
        $this->assertInstanceOf(Draft::class, $school->fresh()->state);
    }
}
