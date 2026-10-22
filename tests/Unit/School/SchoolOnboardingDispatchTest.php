<?php

namespace Tests\Unit\School;

use App\Jobs\SetupSchoolOnboarding;
use App\Models\School;
use App\Services\School\SchoolLifecycleService;
use App\States\School\Active;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolOnboardingDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('schools')) {
            $this->markTestSkipped('schools table required');
        }
    }

    private function makeActiveSchool(string $name = 'Onboard Me'): School
    {
        $school = app(SchoolLifecycleService::class)->createDraft(['name' => $name]);
        $school->state = Active::class;
        $school->is_active = true;
        $school->save();

        return $school->fresh();
    }

    public function test_duplicate_ensure_dispatches_only_once(): void
    {
        Queue::fake();
        $school = $this->makeActiveSchool();
        $lifecycle = app(SchoolLifecycleService::class);

        $lifecycle->ensureOperationalOnboarding($school);
        $lifecycle->ensureOperationalOnboarding($school->fresh());

        Queue::assertPushed(SetupSchoolOnboarding::class, 1);
        $data = $school->fresh()->data ?? [];
        $this->assertNotEmpty($data['operational_onboarding_dispatch_claimed_at'] ?? null);
    }

    public function test_completed_school_is_not_redispatched(): void
    {
        Queue::fake();
        $school = $this->makeActiveSchool('Already Done');
        $school->data = array_merge($school->data ?? [], [
            'operational_onboarding_completed_at' => now()->toIso8601String(),
        ]);
        $school->save();

        app(SchoolLifecycleService::class)->ensureOperationalOnboarding($school->fresh());

        Queue::assertNotPushed(SetupSchoolOnboarding::class);
    }

    public function test_dispatch_exception_clears_claim_and_allows_retry(): void
    {
        $school = $this->makeActiveSchool('Dispatch Fail');

        $dispatcher = \Mockery::mock(BusDispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new \RuntimeException('simulated dispatch failure'));
        $this->app->instance(BusDispatcher::class, $dispatcher);

        $lifecycle = app(SchoolLifecycleService::class);

        try {
            $lifecycle->ensureOperationalOnboarding($school);
            $this->fail('Expected RuntimeException from dispatch');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated dispatch failure', $e->getMessage());
        }

        $data = $school->fresh()->data ?? [];
        $this->assertArrayNotHasKey('operational_onboarding_dispatch_claimed_at', $data);
        $this->assertArrayNotHasKey('operational_onboarding_dispatched_at', $data);
        $this->assertArrayNotHasKey('operational_onboarding_completed_at', $data);

        // Retry with a real (faked) queue must succeed in claiming + pushing.
        Queue::fake();
        // Re-bind real dispatcher under Queue::fake
        $this->app->forgetInstance(BusDispatcher::class);
        app(SchoolLifecycleService::class)->ensureOperationalOnboarding($school->fresh());
        Queue::assertPushed(SetupSchoolOnboarding::class, 1);
    }

    public function test_job_failure_clears_claim_and_does_not_set_completed(): void
    {
        $school = $this->makeActiveSchool('Job Fail');
        $school->data = array_merge($school->data ?? [], [
            'operational_onboarding_dispatch_claimed_at' => now()->toIso8601String(),
        ]);
        $school->save();

        $job = new class($school) extends SetupSchoolOnboarding
        {
            protected function createDefaultSections(): void
            {
                throw new \RuntimeException('section bootstrap failed');
            }
        };

        try {
            $job->handle();
            $this->fail('Expected job to rethrow');
        } catch (\RuntimeException $e) {
            $this->assertSame('section bootstrap failed', $e->getMessage());
        }

        $data = $school->fresh()->data ?? [];
        $this->assertArrayNotHasKey('operational_onboarding_dispatch_claimed_at', $data);
        $this->assertArrayNotHasKey('operational_onboarding_completed_at', $data);

        // Retry dispatch is allowed after claim cleared.
        Queue::fake();
        app(SchoolLifecycleService::class)->ensureOperationalOnboarding($school->fresh());
        Queue::assertPushed(SetupSchoolOnboarding::class, 1);
    }

    public function test_synchronous_job_completion_preserves_completed_marker(): void
    {
        // Run the real job body (not Queue::fake) when structural tables exist.
        if (! Schema::hasTable('school_sections') || ! Schema::hasTable('academic_sessions')) {
            $this->markTestSkipped('school_sections and academic_sessions required for sync onboarding job');
        }

        config(['queue.default' => 'sync']);

        $school = $this->makeActiveSchool('Sync Complete');
        app(SchoolLifecycleService::class)->ensureOperationalOnboarding($school);

        $fresh = $school->fresh();
        $data = $fresh->data ?? [];
        $this->assertNotEmpty($data['operational_onboarding_completed_at'] ?? null);
        $this->assertArrayNotHasKey('operational_onboarding_dispatch_claimed_at', $data);

        Queue::fake();
        app(SchoolLifecycleService::class)->ensureOperationalOnboarding($fresh);
        Queue::assertNotPushed(SetupSchoolOnboarding::class);
    }

    public function test_clear_dispatch_claim_preserves_completed_marker(): void
    {
        $school = $this->makeActiveSchool('Claim Cleanup Race');
        $school->data = array_merge($school->data ?? [], [
            'operational_onboarding_dispatch_claimed_at' => now()->toIso8601String(),
            'operational_onboarding_completed_at' => now()->toIso8601String(),
        ]);
        $school->save();

        $job = new class($school) extends SetupSchoolOnboarding
        {
            public function exposeClearClaim(): void
            {
                $this->clearDispatchClaim();
            }
        };
        $job->exposeClearClaim();

        $data = $school->fresh()->data ?? [];
        $this->assertNotEmpty($data['operational_onboarding_completed_at'] ?? null);
        // Claim may remain or be absent; completed must survive.
        $this->assertSame(
            $school->data['operational_onboarding_completed_at'],
            $data['operational_onboarding_completed_at']
        );
    }
}
