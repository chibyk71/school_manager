<?php

namespace App\Services\School;

use App\Jobs\SetupSchoolOnboarding;
use App\Models\School;
use App\States\School\Active;
use App\States\School\Archived;
use App\States\School\Draft;
use App\States\School\Ready;
use App\States\School\SchoolState;
use App\States\School\Suspended;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates School lifecycle transitions with readiness, capacity and authorization.
 *
 * Clients never assign arbitrary states; only these explicit operations mutate state.
 * Capacity-affecting operations always run under SchoolCapacityService::withCapacityLock.
 */
class SchoolLifecycleService
{
    public function __construct(
        private readonly SchoolReadinessEvaluator $readiness,
        private readonly SchoolCapacityService $capacity,
    ) {}

    /**
     * Draft → Ready when all six readiness conditions pass.
     */
    public function markReady(School $school): School
    {
        return DB::transaction(function () use ($school) {
            $school = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();

            if (! ($school->state instanceof Draft)) {
                throw ValidationException::withMessages([
                    'state' => 'Only a Draft school can be marked Ready.',
                ]);
            }

            $result = $this->readiness->evaluate($school);
            if (! $result['ready']) {
                throw ValidationException::withMessages(
                    $this->readinessMessages($result)
                );
            }

            $school->state->transitionTo(Ready::class);
            $this->syncIsActive($school);
            $school->save();

            Log::info('School marked Ready', ['school_id' => $school->id]);

            return $school->fresh();
        });
    }

    /**
     * Ready → Active (explicit activation).
     */
    public function activate(School $school): School
    {
        $school = DB::transaction(function () use ($school) {
            $school = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();

            if (! ($school->state instanceof Ready)) {
                throw ValidationException::withMessages([
                    'state' => 'Only a Ready school can be activated.',
                ]);
            }

            $school->state->transitionTo(Active::class);
            $this->syncIsActive($school);
            $school->save();

            Log::info('School activated', ['school_id' => $school->id]);

            return $school->fresh();
        });

        $this->ensureOperationalOnboarding($school);

        return $school->fresh();
    }

    /**
     * Active → Suspended.
     */
    public function suspend(School $school): School
    {
        return DB::transaction(function () use ($school) {
            $school = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();

            if (! ($school->state instanceof Active)) {
                throw ValidationException::withMessages([
                    'state' => 'Only an Active school can be suspended.',
                ]);
            }

            $school->state->transitionTo(Suspended::class);
            $this->syncIsActive($school);
            $school->save();

            Log::info('School suspended', ['school_id' => $school->id]);

            return $school->fresh();
        });
    }

    /**
     * Suspended → Active.
     */
    public function reactivate(School $school): School
    {
        return DB::transaction(function () use ($school) {
            $school = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();

            if (! ($school->state instanceof Suspended)) {
                throw ValidationException::withMessages([
                    'state' => 'Only a Suspended school can be reactivated.',
                ]);
            }

            $school->state->transitionTo(Active::class);
            $this->syncIsActive($school);
            $school->save();

            Log::info('School reactivated', ['school_id' => $school->id]);

            return $school->fresh();
        });
    }

    /**
     * Active|Suspended → Archived. Releases capacity.
     */
    public function archive(School $school): School
    {
        return $this->capacity->withCapacityLock(function () use ($school) {
            $school = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();

            if (! ($school->state instanceof Active || $school->state instanceof Suspended)) {
                throw ValidationException::withMessages([
                    'state' => 'Only an Active or Suspended school can be archived.',
                ]);
            }

            $school->state->transitionTo(Archived::class);
            $this->syncIsActive($school);
            $school->save();

            Log::info('School archived', ['school_id' => $school->id]);

            return $school->fresh();
        });
    }

    /**
     * Archived → Draft|Ready based on current readiness. Consumes capacity.
     */
    public function recover(School $school): School
    {
        return $this->capacity->withCapacityLock(function () use ($school) {
            $school = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();

            if (! ($school->state instanceof Archived)) {
                throw ValidationException::withMessages([
                    'state' => 'Only an Archived school can be recovered.',
                ]);
            }

            $this->capacity->assertCanConsume(1);

            $ready = $this->readiness->isReady($school);
            $target = $ready ? Ready::class : Draft::class;

            $school->state->transitionTo($target);
            $this->syncIsActive($school);
            $school->save();

            Log::info('School recovered', [
                'school_id' => $school->id,
                'target' => $ready ? Ready::$name : Draft::$name,
            ]);

            return $school->fresh();
        });
    }

    /**
     * After a readiness-critical update on a Ready school, demote to Draft if checklist fails.
     */
    public function reevaluateAfterUpdate(School $school): School
    {
        return DB::transaction(function () use ($school) {
            $school = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();

            if (! ($school->state instanceof Ready)) {
                return $school;
            }

            if ($this->readiness->isReady($school)) {
                return $school;
            }

            $school->state->transitionTo(Draft::class);
            $this->syncIsActive($school);
            $school->save();

            Log::info('School demoted Ready → Draft after readiness-critical update', [
                'school_id' => $school->id,
            ]);

            return $school->fresh();
        });
    }

    /**
     * Create a Draft school (name required). Capacity-checked.
     *
     * @param  array<string, mixed>  $data
     */

    /**
     * Operational onboarding (sections, class levels, session) runs once when the
     * school first becomes Active — not on Draft creation.
     *
     * Safety:
     * - Skip only when operational_onboarding_completed_at is set (job finished).
     * - Concurrent activators: row lock + dispatch claim so only one dispatches.
     * - Dispatch failure clears the claim so retries remain possible.
     * - After dispatch, re-read the row so a synchronous job's completed_at is not overwritten.
     */
    public function ensureOperationalOnboarding(School $school): void
    {
        $shouldDispatch = false;

        DB::transaction(function () use ($school, &$shouldDispatch) {
            $locked = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();
            $data = $locked->data ?? [];

            if (! empty($data['operational_onboarding_completed_at'])) {
                return;
            }

            // Another activator already claimed an in-flight dispatch.
            if (! empty($data['operational_onboarding_dispatch_claimed_at'])) {
                return;
            }

            $data['operational_onboarding_dispatch_claimed_at'] = now()->toIso8601String();
            $locked->data = $data;
            $locked->save();
            $shouldDispatch = true;
        });

        if (! $shouldDispatch) {
            return;
        }

        try {
            SetupSchoolOnboarding::dispatch($school->fresh());
        } catch (\Throwable $e) {
            // Failed dispatch must not permanently suppress retries.
            DB::transaction(function () use ($school) {
                $locked = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();
                $data = $locked->data ?? [];
                if (empty($data['operational_onboarding_completed_at'])) {
                    unset($data['operational_onboarding_dispatch_claimed_at'], $data['operational_onboarding_dispatched_at']);
                    $locked->data = $data;
                    $locked->save();
                }
            });
            Log::error('Operational onboarding dispatch failed; claim cleared for retry', [
                'school_id' => $school->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Sync drivers may have completed the job already — never clobber completed_at.
        DB::transaction(function () use ($school) {
            $locked = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();
            $data = $locked->data ?? [];

            if (! empty($data['operational_onboarding_completed_at'])) {
                return;
            }

            $data['operational_onboarding_dispatched_at'] = now()->toIso8601String();
            $locked->data = $data;
            $locked->save();
        });

        Log::info('Operational onboarding dispatched on activate', [
            'school_id' => $school->id,
        ]);
    }

    public function createDraft(array $data): School
    {
        return $this->capacity->withCapacityLock(function () use ($data) {
            $this->capacity->assertCanConsume(1);

            $school = new School([
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'email' => $data['email'] ?? null,
                'phone_one' => $data['phone_one'] ?? null,
                'phone_two' => $data['phone_two'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'type' => $data['type'] ?? null,
                'description' => $data['description'] ?? null,
                'motto' => $data['motto'] ?? null,
                'data' => $data['extra_data'] ?? [],
                'is_active' => false,
            ]);

            // State defaults to Draft via model cast / column default.
            $school->state = Draft::class;
            $school->save();

            if (! empty($data['addresses']) && is_array($data['addresses'])) {
                foreach ($data['addresses'] as $addrData) {
                    if (! is_array($addrData)) {
                        continue;
                    }
                    $isPrimary = (bool) ($addrData['is_primary'] ?? false);
                    unset($addrData['is_primary'], $addrData['id']);
                    $school->addAddress($addrData, $isPrimary);
                }
            }

            Log::info('Draft school created', ['school_id' => $school->id]);

            return $school->fresh();
        });
    }

    /**
     * Soft-delete with capacity release when the school was in a counted state.
     */
    public function softDelete(School $school): void
    {
        $this->capacity->withCapacityLock(function () use ($school) {
            $school = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();
            $school->delete();
        });
    }

    /**
     * Restore soft-deleted school; recheck capacity; leave state as stored
     * (or Draft if previously archived-while-deleted path needs recovery separately).
     */
    public function restore(School $school): School
    {
        return $this->capacity->withCapacityLock(function () use ($school) {
            $school = School::withTrashed()->whereKey($school->id)->lockForUpdate()->firstOrFail();

            if (! $school->trashed()) {
                throw ValidationException::withMessages([
                    'school' => 'School is not soft-deleted.',
                ]);
            }

            // If restored school would be in a counted state, consume capacity.
            $stateName = $school->state instanceof SchoolState
                ? $school->state::$name
                : (string) $school->getRawOriginal('state');

            if (in_array($stateName, SchoolCapacityService::countedStateNames(), true)) {
                $this->capacity->assertCanConsume(1);
            }

            $school->restore();
            $this->syncIsActive($school);
            $school->save();

            Log::info('School restored', ['school_id' => $school->id]);

            return $school->fresh();
        });
    }

    /**
     * Keep legacy is_active aligned with lifecycle without being a second source of truth.
     * Active and Suspended are "operationally present"; Draft/Ready/Archived are not fully live.
     */
    private function syncIsActive(School $school): void
    {
        $school->is_active = $school->state instanceof Active
            || $school->state instanceof Suspended;
    }

    /**
     * @param  array{ready: bool, conditions: array<string, array{passed: bool, message: string|null}>}  $result
     * @return array<string, string>
     */
    private function readinessMessages(array $result): array
    {
        $messages = [];
        foreach ($result['conditions'] as $key => $condition) {
            if (! $condition['passed'] && $condition['message']) {
                $messages[$key] = $condition['message'];
            }
        }

        if ($messages === []) {
            $messages['readiness'] = 'School does not meet readiness requirements.';
        }

        return $messages;
    }
}
