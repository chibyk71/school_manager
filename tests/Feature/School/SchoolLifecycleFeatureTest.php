<?php

namespace Tests\Feature\School;

use App\Jobs\SetupSchoolOnboarding;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\School\SchoolLifecycleService;
use App\States\School\Active;
use App\States\School\Archived;
use App\States\School\Draft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use App\Support\Entitlements\DefaultEntitlementProfile;
use App\Support\Entitlements\CapacityEvaluator;
use App\Support\Entitlements\EntitlementResolver;
use App\Services\School\SchoolCapacityService;
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

        // SchoolPolicy::create requires super-admin or school.create (tenant scope).
        // Do not use can('create', School::class) / Gate::before with School::class —
        // Laratrust resolves the class string as a team primary key and 404s.
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
        } else {
            $permission = Permission::query()->firstOrCreate(
                ['name' => 'school.create'],
                ['display_name' => 'school.create']
            );
            if (Schema::hasTable('permission_user')) {
                DB::table('permission_user')->insertOrIgnore([
                    'permission_id' => $permission->id,
                    'user_id' => $user->id,
                    'user_type' => User::class,
                    'school_id' => null,
                ]);
            }
        }

        $this->actingAs($user->fresh());

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

        $response->assertRedirect();
        $this->assertTrue(
            $response->isRedirect() && (
                session()->has('success')
                || School::query()->where('name', 'Name Only Draft School')->exists()
            ),
            'expected success flash or persisted school'
        );

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

        $response = $this->from('/schools/'.$school->id)->put(route('schools.update', $school), [
            'name' => 'Incomplete Draft Updated',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('errors');
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
        $school->state = Active::class;
        $school->is_active = true;
        $school->save();

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->from('/schools/'.$school->id)->post(route('schools.archive', $school));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertInstanceOf(Archived::class, $school->fresh()->state);
    }

    public function test_json_lifecycle_action_returns_json_without_inertia_header(): void
    {
        $this->actingAsAuthorized();
        $school = app(SchoolLifecycleService::class)->createDraft(['name' => 'JSON Lifecycle']);
        $school->state = Active::class;
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

        $response = $this->deleteJson(route('schools.destroy').'?ids[]='.$school->id);

        $response->assertForbidden();
        $this->assertFalse($school->fresh()->trashed());
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
        $role = Role::query()->firstOrCreate(
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
        $outsiderResponse = $this->deleteJson(route('schools.destroy').'?ids[]='.$school2->id);
        $outsiderResponse->assertForbidden();
        $this->assertFalse($school2->fresh()->trashed());
    }

    public function test_bulk_toggle_status_is_gone(): void
    {
        $this->actingAsAuthorized();

        $response = $this->postJson(route('schools.bulk-toggle'), [
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

        $response = $this->postJson(route('schools.mark-ready', $school));

        $response->assertStatus(422);
        $this->assertInstanceOf(Draft::class, $school->fresh()->state);
    }

    public function test_unauthorized_authenticated_user_cannot_create_school(): void
    {
        // Authenticated but no super-admin role and no school.create permission.
        $user = User::factory()->create();
        $this->actingAs($user);

        $before = School::query()->count();

        $response = $this->post(route('schools.store'), [
            'name' => 'Unauthorized Create Attempt',
        ]);

        $response->assertForbidden();
        $this->assertSame($before, School::query()->count());
        $this->assertNull(
            School::query()->where('name', 'Unauthorized Create Attempt')->first()
        );
    }

    public function test_inertia_create_redirects_with_success_flash(): void
    {
        $this->actingAsAuthorized();

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post(route('schools.store'), [
            'name' => 'Inertia Create School',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        // Must not be a plain JSON body for an Inertia visit
        $this->assertStringNotContainsString(
            'application/json',
            strtolower((string) $response->headers->get('Content-Type', '')),
            'Inertia create must not return application/json'
        );

        $school = School::query()->where('name', 'Inertia Create School')->first();
        $this->assertNotNull($school);
        $this->assertInstanceOf(Draft::class, $school->state);
    }

    public function test_json_create_without_inertia_returns_json(): void
    {
        $this->actingAsAuthorized();

        $response = $this->postJson(route('schools.store'), [
            'name' => 'Json Create School',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'School created successfully!');
        $this->assertNotNull(
            School::query()->where('name', 'Json Create School')->first()
        );
    }

    public function test_create_when_capacity_exhausted_returns_422_and_persists_nothing(): void
    {
        $this->actingAsAuthorized();

        // Cap at zero additional schools via entitlement profile used by CapacityEvaluator.
        $this->app->instance(
            DefaultEntitlementProfile::class,
            new DefaultEntitlementProfile([
                DefaultEntitlementProfile::SCHOOLS_MAX_COUNT_KEY => 0,
            ])
        );
        $this->app->forgetInstance(SchoolCapacityService::class);
        $this->app->forgetInstance(SchoolLifecycleService::class);
        $this->app->forgetInstance(EntitlementResolver::class);
        $this->app->forgetInstance(CapacityEvaluator::class);

        $before = School::query()->count();

        $response = $this->from(route('schools.create'))->post(route('schools.store'), [
            'name' => 'Over Capacity School',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($before, School::query()->count());
        $this->assertNull(
            School::query()->where('name', 'Over Capacity School')->first()
        );

        // Pure JSON client gets 422, not 500
        $json = $this->postJson(route('schools.store'), [
            'name' => 'Over Capacity Json School',
        ]);
        $json->assertStatus(422);
        $json->assertJsonFragment(['message' => 'School capacity limit reached. No additional schools can be created until capacity is available.']);
        $this->assertNull(
            School::query()->where('name', 'Over Capacity Json School')->first()
        );
    }


    public function test_draft_create_does_not_change_active_school_context(): void
    {
        $this->actingAsAuthorized();

        $existing = app(SchoolLifecycleService::class)->createDraft(['name' => 'Existing Active Context']);
        session(['active_school_id' => $existing->id]);
        $this->assertSame($existing->id, session('active_school_id'));

        $response = $this->post(route('schools.store'), [
            'name' => 'New Draft Must Not Steal Context',
        ]);
        $response->assertRedirect();

        $this->assertSame(
            $existing->id,
            session('active_school_id'),
            'Creating a Draft must not replace the current active school context'
        );
        $this->assertNotNull(
            School::query()->where('name', 'New Draft Must Not Steal Context')->first()
        );
    }

    public function test_malformed_nested_address_returns_validation_not_500(): void
    {
        $this->actingAsAuthorized();
        $before = School::query()->count();

        $response = $this->from(route('schools.create'))->post(route('schools.store'), [
            'name' => 'Bad Address School',
            'addresses' => [
                [
                    // missing required address_line_1 and type
                    'city_text' => 'Nowhere',
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame($before, School::query()->count());
        $this->assertNull(
            School::query()->where('name', 'Bad Address School')->first()
        );

        $json = $this->postJson(route('schools.store'), [
            'name' => 'Bad Address Json School',
            'addresses' => [
                ['city_text' => 'Nowhere'],
            ],
        ]);
        $json->assertStatus(422);
        $this->assertNull(
            School::query()->where('name', 'Bad Address Json School')->first()
        );
    }

    public function test_activate_on_active_school_retries_onboarding_without_error_state(): void
    {
        $this->actingAsAuthorized();
        $lifecycle = app(SchoolLifecycleService::class);

        $school = $lifecycle->createDraft(['name' => 'Onboarding Retry School']);
        // Force Active without going through full Ready checklist in this focused test.
        $school->state = \App\States\School\Active::class;
        $school->is_active = true;
        $school->data = [
            // No completed_at, no claim — incomplete onboarding.
        ];
        $school->save();

        $this->assertInstanceOf(\App\States\School\Active::class, $school->fresh()->state);

        $response = $this->post(route('schools.activate', $school));
        $response->assertRedirect();
        $this->assertInstanceOf(\App\States\School\Active::class, $school->fresh()->state);
    }

}
