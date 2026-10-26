<?php

namespace Tests\Feature\School;

use App\Models\User;
use App\Services\DynamicEnum\DynamicEnumLifecycleService;
use App\Services\School\SchoolLifecycleService;
use App\Services\School\SchoolReadinessEvaluator;
use App\States\School\Draft;
use App\States\School\Ready;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Endpoint-level regression: unsetting primary via AddressController demotes Ready → Draft.
 */
class SchoolAddressUnsetPrimaryDemotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unset_primary_endpoint_demotes_ready_school_to_draft(): void
    {
        if (! Schema::hasTable('schools') || ! Schema::hasTable('addresses')) {
            $this->markTestSkipped('schools and addresses tables required');
        }

        $user = User::factory()->create();
        Gate::before(fn () => true);
        $this->actingAs($user);

        if (class_exists(DynamicEnumLifecycleService::class) && Schema::hasTable('dynamic_enums')) {
            $enum = app(DynamicEnumLifecycleService::class);
            $def = $enum->ensureDefinition('address.type', 'Address type');
            $enum->createTenantOption($def, 'physical', 'Physical', ['sort_order' => 1]);
        }

        $lifecycle = app(SchoolLifecycleService::class);
        $school = $lifecycle->createDraft([
            'name' => 'Ready Endpoint School',
            'code' => 'RES1',
            'email' => 'res@example.com',
            'type' => 'private',
            'contact_name' => 'Contact Person',
            'phone_one' => '08012345678',
        ]);

        // type is Dynamic Enum–backed; omit when catalogue not seeded in tests.
        $address = $school->addAddress([
            'address_line_1' => '1 Endpoint Road',
            'city' => 'Test City',
            'type' => 'physical',
        ], true);
        $school = $school->fresh();

        if (! app(SchoolReadinessEvaluator::class)->isReady($school)) {
            $this->markTestSkipped('Could not satisfy readiness checklist in this schema');
        }

        $school = $lifecycle->markReady($school);
        $this->assertInstanceOf(Ready::class, $school->state);
        $this->assertNotNull($school->primaryAddress());

        $response = $this->deleteJson(
            route('addresses.unset-primary', [
                'owner' => 'school',
                'ownerId' => $school->id,
                'address' => $address->id,
            ])
        );

        $response->assertNoContent();

        $school = $school->fresh();
        $this->assertNull($school->primaryAddress());
        $this->assertInstanceOf(Draft::class, $school->state);
    }
}
