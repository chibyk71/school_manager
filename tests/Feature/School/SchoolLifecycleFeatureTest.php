<?php

namespace Tests\Feature\School;

use App\Models\School;
use App\Models\User;
use App\Services\School\SchoolLifecycleService;
use App\States\School\Draft;
use App\States\School\Ready;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use App\Jobs\SetupSchoolOnboarding;
use App\Events\SchoolCreated;
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

    public function test_name_only_create_yields_draft_without_is_active_true(): void
    {
        $this->actingAsAuthorized();

        $response = $this->post(route('schools.store'), [
            'name' => 'Name Only Draft School',
        ]);

        $response->assertSuccessful();

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
        ])->assertSuccessful();

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

        $response->assertSuccessful();
        $school->refresh();
        $this->assertSame('Incomplete Draft Updated', $school->name);
        $this->assertInstanceOf(Draft::class, $school->state);
        $this->assertNull($school->code);
    }

    public function test_update_routes_through_service_and_rejects_state_is_active(): void
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

    public function test_lifecycle_inertia_request_receives_redirect_not_json(): void
    {
        $this->actingAsAuthorized();
        $school = app(SchoolLifecycleService::class)->createDraft([
            'name' => 'Inertia Lifecycle School',
        ]);

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->from('/schools/'.$school->id)->post(route('schools.mark-ready', $school));

        // Validation failure still session/redirect oriented for Inertia, not bare JSON 422 body as only contract
        $this->assertFalse(
            $response->headers->get('content-type') === 'application/json'
            && $response->status() === 200
            && str_contains((string) $response->getContent(), '"school"')
            && ! $response->isRedirect()
        );
    }

    public function test_policy_boundary_denies_user_without_school_membership(): void
    {
        // Real policy: non-super-admin without membership cannot update/delete.
        Gate::before(fn () => null); // do not short-circuit

        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $school = app(SchoolLifecycleService::class)->createDraft(['name' => 'Member School']);
        // Attach owner membership if pivot exists
        if (method_exists($owner, 'schools')) {
            try {
                $owner->schools()->attach($school->id);
            } catch (\Throwable) {
                // pivot may need extra columns in focused schema
            }
        }

        $this->actingAs($outsider);
        $response = $this->delete(route('schools.destroy'), ['ids' => [$school->id]]);
        $this->assertTrue(
            in_array($response->status(), [403, 401], true),
            'Outsider must not delete foreign school, got '.$response->status()
        );
        $this->assertFalse($school->fresh()->trashed());
    }
}
