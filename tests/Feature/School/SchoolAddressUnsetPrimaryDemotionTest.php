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
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Endpoint-level regression: AddressController unset/delete primary is atomic with demotion.
 */
class SchoolAddressUnsetPrimaryDemotionTest extends TestCase
{
    use RefreshDatabase;

    private function seedReadySchoolWithPrimary(): array
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

        return [$school, $address];
    }

    public function test_unset_primary_endpoint_demotes_ready_school_to_draft(): void
    {
        [$school, $address] = $this->seedReadySchoolWithPrimary();

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

    public function test_reevaluation_failure_rolls_back_unset_primary_on_ready_school(): void
    {
        [$school, $address] = $this->seedReadySchoolWithPrimary();
        $primaryId = $address->id;

        $real = app(SchoolLifecycleService::class);
        $mock = Mockery::mock($real)->makePartial();
        $mock->shouldReceive('reevaluateAfterUpdate')
            ->once()
            ->andThrow(new RuntimeException('forced readiness reevaluation failure'));
        $this->app->instance(SchoolLifecycleService::class, $mock);

        $this->withoutExceptionHandling();
        try {
            $this->deleteJson(
                route('addresses.unset-primary', [
                    'owner' => 'school',
                    'ownerId' => $school->id,
                    'address' => $primaryId,
                ])
            );
            $this->fail('Expected RuntimeException from forced reevaluation failure');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('forced readiness reevaluation failure', $e->getMessage());
        }

        $school = $school->fresh();
        $this->assertInstanceOf(Ready::class, $school->state, 'school must remain Ready when reevaluation rolls back');
        $this->assertNotNull($school->primaryAddress(), 'primary address must not be lost on rollback');
        $this->assertSame($primaryId, $school->primaryAddress()->id);
    }
}
