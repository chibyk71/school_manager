<?php

namespace Tests\Unit\School;

use App\Models\School;
use App\Services\School\SchoolLifecycleService;
use App\Services\School\SchoolReadinessEvaluator;
use App\States\School\Draft;
use App\States\School\Ready;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolPrimaryAddressUnsetDemotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsetting_primary_address_demotes_ready_school_to_draft(): void
    {
        if (! Schema::hasTable('schools') || ! Schema::hasTable('addresses')) {
            $this->markTestSkipped('schools and addresses tables required');
        }

        $lifecycle = app(SchoolLifecycleService::class);
        $school = $lifecycle->createDraft([
            'name' => 'Ready With Address',
            'code' => 'RWA1',
            'email' => 'rwa@example.com',
            'type' => 'private',
            'contact_name' => 'Contact Person',
            'phone_one' => '08012345678',
        ]);

        if (class_exists(\App\Services\DynamicEnum\DynamicEnumLifecycleService::class)
            && Schema::hasTable('dynamic_enums')) {
            $enum = app(\App\Services\DynamicEnum\DynamicEnumLifecycleService::class);
            $def = $enum->ensureDefinition('address.type', 'Address type');
            $enum->createTenantOption($def, 'physical', 'Physical', ['sort_order' => 1]);
        }

        $address = $school->addAddress([
            'address_line_1' => '1 Test Road',
            'city_text' => 'Test City',
            'type' => 'physical',
        ], true);
        $school = $school->fresh();

        if (! app(SchoolReadinessEvaluator::class)->isReady($school)) {
            $this->markTestSkipped('Could not satisfy readiness checklist in this schema');
        }

        $school = $lifecycle->markReady($school);
        $this->assertInstanceOf(Ready::class, $school->state);

        // Same path AddressController::unsetPrimary uses after mutation.
        $school->unsetPrimaryAddress();
        $school = $lifecycle->reevaluateAfterUpdate($school->fresh());

        $this->assertInstanceOf(Draft::class, $school->state);
        $this->assertNull($school->primaryAddress());
    }
}
