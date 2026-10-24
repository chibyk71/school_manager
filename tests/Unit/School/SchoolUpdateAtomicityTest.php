<?php

namespace Tests\Unit\School;

use App\Models\School;
use App\Services\School\SchoolCapacityService;
use App\Services\School\SchoolLifecycleService;
use App\Services\School\SchoolReadinessEvaluator;
use App\Services\SchoolService;
use App\States\School\Draft;
use App\States\School\Ready;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * updateSchool must lock + save + reevaluate in one transaction.
 */
class SchoolUpdateAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_update_persists_name_on_draft(): void
    {
        if (! Schema::hasTable('schools')) {
            $this->markTestSkipped('schools table required');
        }

        $lifecycle = app(SchoolLifecycleService::class);
        $school = $lifecycle->createDraft(['name' => 'Atomic Draft']);

        $updated = app(SchoolService::class)->updateSchool($school, [
            'name' => 'Atomic Draft Renamed',
        ]);

        $this->assertSame('Atomic Draft Renamed', $updated->fresh()->name);
        $this->assertInstanceOf(Draft::class, $updated->fresh()->state);
    }

    public function test_clearing_code_on_ready_demotes_within_update(): void
    {
        if (! Schema::hasTable('schools') || ! Schema::hasTable('addresses')) {
            $this->markTestSkipped('schools and addresses required');
        }

        if (class_exists(\App\Services\DynamicEnum\DynamicEnumLifecycleService::class)
            && Schema::hasTable('dynamic_enums')) {
            $enum = app(\App\Services\DynamicEnum\DynamicEnumLifecycleService::class);
            $def = $enum->ensureDefinition('address.type', 'Address type');
            $enum->createTenantOption($def, 'physical', 'Physical', ['sort_order' => 1]);
        }

        $lifecycle = app(SchoolLifecycleService::class);
        $school = $lifecycle->createDraft([
            'name' => 'Ready Atomic',
            'code' => 'ATM1',
            'email' => 'atm@example.com',
            'type' => 'private',
            'contact_name' => 'Contact Person',
            'phone_one' => '08012345678',
        ]);
        $school->addAddress([
            'address_line_1' => '1 Atomic Road',
            'city' => 'City',
            'type' => 'physical',
        ], true);
        $school = $lifecycle->markReady($school->fresh());
        $this->assertInstanceOf(Ready::class, $school->state);

        $updated = app(SchoolService::class)->updateSchool($school, [
            'name' => 'Ready Atomic',
            'code' => null,
        ]);

        $this->assertNull($updated->fresh()->code);
        $this->assertInstanceOf(Draft::class, $updated->fresh()->state);
    }

    public function test_reevaluation_exception_rolls_back_attribute_changes(): void
    {
        if (! Schema::hasTable('schools') || ! Schema::hasTable('addresses')) {
            $this->markTestSkipped('schools and addresses required');
        }

        if (class_exists(\App\Services\DynamicEnum\DynamicEnumLifecycleService::class)
            && Schema::hasTable('dynamic_enums')) {
            $enum = app(\App\Services\DynamicEnum\DynamicEnumLifecycleService::class);
            $def = $enum->ensureDefinition('address.type', 'Address type');
            $enum->createTenantOption($def, 'physical', 'Physical', ['sort_order' => 1]);
        }

        // Build Ready school with the real lifecycle (before installing failure injection).
        $lifecycle = app(SchoolLifecycleService::class);
        $school = $lifecycle->createDraft([
            'name' => 'Rollback School',
            'code' => 'RB1',
            'email' => 'rb@example.com',
            'type' => 'private',
            'contact_name' => 'Contact Person',
            'phone_one' => '08012345678',
        ]);
        $school->addAddress([
            'address_line_1' => '1 Rollback Road',
            'city' => 'City',
            'type' => 'physical',
        ], true);
        $school = $lifecycle->markReady($school->fresh());
        $this->assertInstanceOf(Ready::class, $school->state);
        $originalName = $school->name;
        $originalCode = $school->code;

        // SchoolReadinessEvaluator is final — inject failure via a lifecycle subclass
        // that throws inside reevaluateAfterUpdate (still inside updateSchool's transaction).
        $this->app->instance(
            SchoolLifecycleService::class,
            new class(
                app(SchoolReadinessEvaluator::class),
                app(SchoolCapacityService::class),
            ) extends SchoolLifecycleService {
                public function reevaluateAfterUpdate(School $school): School
                {
                    throw new RuntimeException('forced reevaluation failure');
                }
            }
        );

        try {
            app(SchoolService::class)->updateSchool($school, [
                'name' => 'Should Not Persist',
                'code' => null,
            ]);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertSame('forced reevaluation failure', $e->getMessage());
        }

        $school->refresh();
        $this->assertSame($originalName, $school->name);
        $this->assertSame($originalCode, $school->code);
        $this->assertInstanceOf(Ready::class, $school->state);
    }
}
