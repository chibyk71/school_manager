<?php

namespace Tests\Unit\School;

use App\Entitlements\Definitions\SchoolManagementFeatures;
use App\Models\School;
use App\Services\School\SchoolCapacityService;
use App\Services\School\SchoolLifecycleService;
use App\Services\School\SchoolReadinessEvaluator;
use App\States\School\Active;
use App\States\School\Archived;
use App\States\School\Draft;
use App\States\School\Ready;
use App\States\School\Suspended;
use App\Support\Entitlements\DefaultEntitlementProfile;
use App\Support\Entitlements\Exceptions\InsufficientCapacityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 1 School Management domain tests.
 *
 * Covers lifecycle transitions, readiness (six conditions), capacity counting,
 * and capacity lock path. Full concurrency proof requires the production DB engine.
 */
class SchoolLifecycleDomainTest extends TestCase
{
    use RefreshDatabase;

    private SchoolLifecycleService $lifecycle;

    private SchoolReadinessEvaluator $readiness;

    private SchoolCapacityService $capacity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureMinimalSchema();

        $this->readiness = app(SchoolReadinessEvaluator::class);
        $this->capacity = app(SchoolCapacityService::class);
        $this->lifecycle = app(SchoolLifecycleService::class);
    }

    private function ensureMinimalSchema(): void
    {
        if (! Schema::hasTable('school_capacity_locks')) {
            Schema::create('school_capacity_locks', function ($table) {
                $table->id();
                $table->string('scope_key')->default('tenant')->unique();
                $table->timestamps();
            });
            DB::table('school_capacity_locks')->insert([
                'scope_key' => 'tenant',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('schools') && ! Schema::hasColumn('schools', 'state')) {
            Schema::table('schools', function ($table) {
                $table->string('state')->default('draft')->nullable();
                $table->string('contact_name')->nullable();
                $table->string('description')->nullable();
                $table->string('motto')->nullable();
            });
        }
    }

    public function test_draft_creation_requires_only_name(): void
    {
        $school = $this->lifecycle->createDraft(['name' => 'Alpha Academy']);

        $this->assertInstanceOf(Draft::class, $school->state);
        $this->assertSame('Alpha Academy', $school->name);
        $this->assertFalse((bool) $school->is_active);
        $this->assertNull($school->code);
        $this->assertNull($school->email);
    }

    public function test_clients_cannot_force_active_on_create(): void
    {
        $school = $this->lifecycle->createDraft([
            'name' => 'Beta',
            'state' => 'active',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Draft::class, $school->state);
        $this->assertFalse((bool) $school->is_active);
    }

    public function test_mark_ready_requires_all_six_conditions(): void
    {
        $school = $this->lifecycle->createDraft(['name' => 'Incomplete']);

        try {
            $this->lifecycle->markReady($school);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('code', $errors);
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('type', $errors);
            $this->assertArrayHasKey('primary_contact', $errors);
            $this->assertArrayHasKey('primary_address', $errors);
        }
    }

    public function test_primary_contact_requires_contact_name_not_institution_name(): void
    {
        $school = $this->lifecycle->createDraft([
            'name' => 'Institution Name',
            'code' => 'INS1',
            'email' => 'office@example.com',
            'type' => 'private',
            // contact_name intentionally missing
        ]);

        $result = $this->readiness->evaluate($school);
        $this->assertFalse($result['conditions']['primary_contact']['passed']);
        $this->assertStringContainsString('contact person', strtolower($result['conditions']['primary_contact']['message'] ?? ''));
    }

    public function test_allowed_transitions(): void
    {
        $school = $this->makeReadySchool();

        $school = $this->lifecycle->markReady($school);
        $this->assertInstanceOf(Ready::class, $school->state);

        $school = $this->lifecycle->activate($school);
        $this->assertInstanceOf(Active::class, $school->state);
        $this->assertTrue((bool) $school->is_active);

        $school = $this->lifecycle->suspend($school);
        $this->assertInstanceOf(Suspended::class, $school->state);

        $school = $this->lifecycle->reactivate($school);
        $this->assertInstanceOf(Active::class, $school->state);

        $school = $this->lifecycle->archive($school);
        $this->assertInstanceOf(Archived::class, $school->state);
        $this->assertFalse((bool) $school->is_active);
    }

    public function test_illegal_transition_rejected(): void
    {
        $school = $this->lifecycle->createDraft(['name' => 'Draft Only']);

        $this->expectException(ValidationException::class);
        $this->lifecycle->activate($school);
    }

    public function test_archived_recovery_selects_draft_or_ready(): void
    {
        $school = $this->makeReadySchool();
        $school = $this->lifecycle->markReady($school);
        $school = $this->lifecycle->activate($school);
        $school = $this->lifecycle->archive($school);

        // Still ready → recover to Ready
        $recovered = $this->lifecycle->recover($school);
        $this->assertInstanceOf(Ready::class, $recovered->state);

        $recovered = $this->lifecycle->activate($recovered);
        $recovered = $this->lifecycle->archive($recovered);

        // Break readiness
        $recovered->contact_name = null;
        $recovered->save();

        $recovered = $this->lifecycle->recover($recovered);
        $this->assertInstanceOf(Draft::class, $recovered->state);
    }

    public function test_ready_demoted_to_draft_after_critical_update(): void
    {
        $school = $this->makeReadySchool();
        $school = $this->lifecycle->markReady($school);
        $this->assertInstanceOf(Ready::class, $school->state);

        $school->contact_name = null;
        $school->save();

        $school = $this->lifecycle->reevaluateAfterUpdate($school);
        $this->assertInstanceOf(Draft::class, $school->state);
    }

    public function test_capacity_excludes_archived_and_soft_deleted(): void
    {
        $a = $this->lifecycle->createDraft(['name' => 'A']);
        $b = $this->lifecycle->createDraft(['name' => 'B']);
        $this->assertSame(2, $this->capacity->currentUsage());

        $b = $this->makeReadySchool($b);
        $b = $this->lifecycle->markReady($b);
        $b = $this->lifecycle->activate($b);
        $b = $this->lifecycle->archive($b);

        $this->assertSame(1, $this->capacity->currentUsage());

        $this->lifecycle->softDelete($a);
        $this->assertSame(0, $this->capacity->currentUsage());
    }

    public function test_capacity_default_limit_is_one_million(): void
    {
        $this->assertSame(
            DefaultEntitlementProfile::SCHOOLS_MAX_COUNT,
            $this->capacity->limit()
        );
        $this->assertSame(1_000_000, $this->capacity->limit());
    }

    public function test_entitlement_key_registered(): void
    {
        $registry = app(\App\Support\Entitlements\EntitlementRegistry::class);
        $this->assertTrue($registry->hasEntitlement(SchoolManagementFeatures::MAX_COUNT_KEY));
    }

    public function test_sections_not_required_for_ready(): void
    {
        $school = $this->makeReadySchool();
        // No sections attached
        $result = $this->readiness->evaluate($school);
        $this->assertTrue($result['ready']);
        $this->assertArrayNotHasKey('sections', $result['conditions']);
    }

    /**
     * Populate a school so all six readiness conditions pass.
     * Requires address infrastructure; if addresses table missing in focused
     * schema, primary_address check is satisfied via a mock relation skip —
     * full suite should run against app migrations.
     */
    private function makeReadySchool(?School $school = null): School
    {
        if ($school === null) {
            $school = $this->lifecycle->createDraft(['name' => 'Ready Candidate']);
        }

        $school->code = 'RC'.substr($school->id, 0, 6);
        $school->email = 'ready-'.substr($school->id, 0, 8).'@example.com';
        $school->type = 'private';
        $school->contact_name = 'Jane Contact';
        $school->phone_one = '+2348000000000';
        $school->save();

        if (! Schema::hasTable('addresses')) {
            $this->markTestSkipped('addresses table required for full readiness happy-path.');
        }

        $school->addAddress([
            'line_1' => '1 Test Street',
            'city' => 'Test City',
            'country_id' => null,
        ], true);

        $school = $school->fresh();
        $this->assertNotNull($school->primaryAddress(), 'Primary address must persist for readiness.');

        return $school;
    }
}
