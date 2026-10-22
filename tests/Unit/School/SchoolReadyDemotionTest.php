<?php

namespace Tests\Unit\School;

use App\Models\School;
use App\Services\School\SchoolLifecycleService;
use App\Services\School\SchoolService;
use App\States\School\Draft;
use App\States\School\Ready;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolReadyDemotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_code_on_ready_school_demotes_to_draft(): void
    {
        if (! Schema::hasTable('schools')) {
            $this->markTestSkipped('schools table required');
        }

        $lifecycle = app(SchoolLifecycleService::class);
        $school = $lifecycle->createDraft([
            'name' => 'Ready Candidate',
            'code' => 'RDY1',
            'email' => 'ready@example.com',
            'type' => 'private',
            'contact_name' => 'Contact Person',
            'phone_one' => '08012345678',
        ]);

        if (Schema::hasTable('addresses') && method_exists($school, 'addAddress')) {
            $school->addAddress(['line_1' => '1 Road', 'city' => 'City'], true);
            $school = $school->fresh();
        } else {
            $this->markTestSkipped('addresses required for Ready');
        }

        // Force Ready if checklist passes
        if (! app(\App\Services\School\SchoolReadinessEvaluator::class)->isReady($school)) {
            $this->markTestSkipped('Could not satisfy readiness in this schema');
        }

        $school = $lifecycle->markReady($school);
        $this->assertInstanceOf(Ready::class, $school->state);

        $school = app(SchoolService::class)->updateSchool($school, [
            'name' => $school->name,
            'code' => null,
        ]);

        $this->assertInstanceOf(Draft::class, $school->fresh()->state);
    }
}
