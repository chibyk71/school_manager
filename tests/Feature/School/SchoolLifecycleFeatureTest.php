<?php

namespace Tests\Feature\School;

use App\Models\School;
use App\Models\User;
use App\States\School\Draft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 request/controller lifecycle feature coverage.
 */
class SchoolLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_client_assigned_is_active_and_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('schools.store'), [
            'name' => 'Draft Only School',
            'is_active' => true,
            'state' => 'active',
        ]);

        // 403 if unauthorized; 422 if prohibited fields fail validation.
        $this->assertTrue(
            in_array($response->status(), [302, 403, 422], true),
            'Unexpected status '.$response->status()
        );

        $school = School::query()->where('name', 'Draft Only School')->first();
        if ($school) {
            $this->assertInstanceOf(Draft::class, $school->state);
            $this->assertFalse((bool) $school->is_active);
        }
    }

    public function test_bulk_toggle_is_retired(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('schools.bulk-toggle'), [
            'ids' => ['00000000-0000-0000-0000-000000000001'],
            'is_active' => true,
        ]);

        $this->assertTrue(
            in_array($response->status(), [403, 410, 422], true),
            'Unexpected status '.$response->status()
        );
    }

    public function test_store_name_only_path_documented(): void
    {
        $this->markTestSkipped(
            'Requires school.create permission/role fixture; exercise via SchoolLifecycleService::createDraft in domain tests.'
        );
    }
}
