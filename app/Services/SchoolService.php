<?php

/**
 * SchoolService — School workspace orchestration within a Tenant deployment.
 *
 * A Tenant is the application/database instance. Multiple Schools share that Tenant.
 * This service manages School lifecycle operations and the authenticated user's active
 * School / Section workspace context. It does not define Tenant identity.
 *
 * Workspace selection for authenticated users requires school_users membership.
 * Authentication itself does not require School membership.
 *
 * Create accepts nested addresses[] and persists via HasAddress (is_primary from payload only;
 * first address is NOT auto-primary). Update covers core school fields only; persisted address
 * mutations use the managed Address API.
 */

namespace App\Services;

use App\Events\SchoolCreated;
use App\Models\School;
use App\Models\SchoolSection;
use App\Models\User;
use App\Notifications\MadeAdminOfSchoolNotification;
use App\Services\School\SchoolLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SchoolService
{
    protected $activeSchool;

    protected $activeSection;

    /**
     * Bind an authorized School as the active workspace context.
     *
     * Purpose: Establish which School scopes subsequent workspace operations (including Section).
     * Architectural rule: authenticated selection requires school_users membership
     * (see SchoolWorkspaceAccess). Guests may bind in-memory context when $persistSession is false.
     *
     * @param  School  $school  The School to activate. Must exist.
     * @param  bool  $persistSession  When true, write active_school_id into the session.
     *                                Guests should pass false so context is request-scoped only.
     * @return void
     *
     * Preconditions:
     * - If a user is authenticated, SchoolWorkspaceAccess::canSelect($user, $school) must be true.
     *
     * Postconditions:
     * - $this->activeSchool references $school.
     * - When $persistSession is true, session active_school_id equals $school->id.
     * - If the previously active School (in-memory or session) differs from $school, Section
     *   context is cleared (in-memory activeSection and session active_section_id).
     *
     * Side effects: session keys active_school_id and possibly active_section_id.
     *
     * @throws \Exception When the authenticated user is not authorized to select $school,
     *                    or when session binding fails.
     */
    public function setActiveSchool(School $school, bool $persistSession = true): void
    {
        try {
            $user = auth()->user();
            if ($user !== null && ! \App\Support\Auth\SchoolWorkspaceAccess::canSelect($user, $school)) {
                throw new \Exception('You are not authorized to select this school workspace.');
            }

            // Invalidate Section when the School identity changes, whether the prior School
            // lived in this service instance or only in the session.
            $previousId = $this->activeSchool?->id ?? session('active_school_id');
            $this->activeSchool = $school;
            if ($previousId !== null && (string) $previousId !== (string) $school->id) {
                $this->activeSection = null;
                session()->forget('active_section_id');
            }
            if ($persistSession) {
                session(['active_school_id' => $school->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to set active school: '.$e->getMessage());
            throw new \Exception($e->getMessage() ?: 'Unable to set active school.');
        }
    }

    /**
     * Clear active School and Section workspace context (in-memory and session).
     *
     * @return void
     *
     * Postconditions: activeSchool and activeSection are null; session keys
     * active_school_id and active_section_id are removed.
     */
    public function clearActiveSchool(): void
    {
        $this->activeSchool = null;
        $this->activeSection = null;
        session()->forget('active_school_id');
        session()->forget('active_section_id');
    }

    /**
     * Clear active Section workspace context only.
     *
     * @return void
     *
     * Postconditions: activeSection is null; session active_section_id is removed.
     * Does not modify the active School.
     */
    public function clearActiveSection(): void
    {
        $this->activeSection = null;
        session()->forget('active_section_id');
    }

    /**
     * Resolve the authorized active School workspace for the current request/user.
     *
     * Resolution order: in-memory activeSchool → session active_school_id → first
     * school_users membership. Raw request headers are never treated as proof of
     * authorization; callers must not pass X-School-Id into this method expecting
     * elevation without SchoolContext middleware checks.
     *
     * @param  Request|null  $request  Optional request (unused for header elevation).
     * @return School|null  Authorized School, or null when none is available/authorized.
     *
     * Side effects: may clear unauthorized session School/Section context; may cache
     * the resolved School on $this->activeSchool.
     */
    public function getActiveSchool(?Request $request = null): ?School
    {
        try {
            if ($this->activeSchool) {
                $user = auth()->user();
                if ($user !== null && ! \App\Support\Auth\SchoolWorkspaceAccess::canSelect($user, $this->activeSchool)) {
                    $this->clearActiveSchool();
                } else {
                    return $this->activeSchool;
                }
            }

            // Never elevate a raw request header to authorized workspace context.
            // Session is only valid if the authenticated user may select that school.
            $sessionId = session('active_school_id');
            if ($sessionId) {
                $school = School::find($sessionId);
                $user = auth()->user();
                if ($school && $user && \App\Support\Auth\SchoolWorkspaceAccess::canSelect($user, $school)) {
                    $this->activeSchool = $school;

                    return $school;
                }
                $this->clearActiveSchool();
            }

            // Membership convenience: first participating school only (authorized by definition).
            $user = auth()->user();
            if ($user) {
                $first = $user->schools()->first();
                if ($first) {
                    $this->activeSchool = $first;

                    return $first;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get active school: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create a new school with optional nested addresses[].
     *
     * @param  array<string, mixed>  $data  Validated data from StoreSchoolRequest
     */
    public function createSchool(array $data): School
    {
        // Phase 1: Draft creation (name required). Capacity + state enforced by lifecycle.
        // Clients cannot assign lifecycle state or force Active on create.
        $lifecycle = app(SchoolLifecycleService::class);
        $school = $lifecycle->createDraft($data);

        // Dispatch after the capacity transaction commits so listeners never
        // re-fetch an uncommitted School (ModelNotFoundException → HTTP 404).
        $schoolId = $school->id;
        $creatorId = auth()->id();
        $creatorId = $creatorId !== null ? (string) $creatorId : null;

        DB::afterCommit(function () use ($schoolId, $creatorId) {
            $reloaded = School::query()->find($schoolId);
            if ($reloaded === null) {
                Log::warning('SchoolCreated skipped: school not found after commit', [
                    'school_id' => $schoolId,
                ]);

                return;
            }

            try {
                event(new SchoolCreated($reloaded, $creatorId));
            } catch (\Throwable $e) {
                Log::error('SchoolCreated listener failed after school create', [
                    'school_id' => $schoolId,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        Log::info('School created successfully', ['school_id' => $school->id]);

        return $school;
    }

    /**
     * Update core school attributes only.
     * Persisted address mutations are handled by the managed Address API (Phase 4).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSchool(School $school, array $data): School
    {
        // Attribute write + readiness reevaluation are one atomic unit:
        // lock the row first, then mutate, so a failed demotion cannot leave
        // readiness-critical changes committed while state stays Ready.
        return DB::transaction(function () use ($school, $data) {
            $school = School::query()->whereKey($school->id)->lockForUpdate()->firstOrFail();

            // Lifecycle state is never assigned from ordinary update payloads.
            unset($data['state'], $data['is_active'], $data['addresses'], $data['primary_address']);

            $assignable = [
                'name', 'code', 'email', 'phone_one', 'phone_two',
                'contact_name', 'type', 'description', 'motto',
            ];

            foreach ($assignable as $field) {
                if (array_key_exists($field, $data)) {
                    $school->{$field} = $data[$field];
                }
            }

            if (array_key_exists('extra_data', $data) && is_array($data['extra_data'])) {
                $school->data = array_merge($school->data ?? [], $data['extra_data']);
            }

            $school->save();

            // Nested transaction uses a savepoint; throw rolls back attribute save too.
            $school = app(SchoolLifecycleService::class)
                ->reevaluateAfterUpdate($school);

            Log::info('School updated successfully', ['school_id' => $school->id]);

            return $school->fresh() ?? $school;
        });
    }

    public function assignAdmin(array $userData, School $school): User
    {
        permitted('school.assign-admin');

        $validated = validator($userData, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.($userData['id'] ?? null),
            'password' => 'nullable|string|min:8',
        ])->validate();

        return DB::transaction(function () use ($validated, $school) {
            $admin = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['name'],
                    'password' => Hash::make($validated['password'] ?? Str::random(12)),
                ]
            );

            if (! $admin->hasRole('admin', $school?->id)) {
                $admin->addRole('admin', $school?->id);
            }

            $admin->schools()->syncWithoutDetaching($school?->id);

            $admin->notify(new MadeAdminOfSchoolNotification($school));

            return $admin;
        });
    }

    /**
     * Bind a Section as the active workspace context under the authorized active School.
     *
     * @param  SchoolSection  $section  Section to activate. Must belong to the active School.
     * @return void
     *
     * Preconditions:
     * - An authorized active School must already be resolved.
     * - $section->school_id must equal the active School id.
     *
     * Postconditions: activeSection is $section; session active_section_id is set.
     *
     * @throws \Exception When no active School is authorized, or the Section belongs to
     *                    a different School, or session binding fails.
     */
    public function setActiveSection(SchoolSection $section): void
    {
        $activeSchool = $this->getActiveSchool();

        if ($activeSchool === null) {
            throw new \Exception('An authorized active school is required before selecting a section.');
        }

        if ((string) $section->school_id !== (string) $activeSchool->id) {
            throw new \Exception('Section does not belong to the active school.');
        }

        try {
            $this->activeSection = $section;
            session(['active_section_id' => $section->id]);
        } catch (\Exception $e) {
            Log::error('Failed to set active section: '.$e->getMessage());
            throw new \Exception($e->getMessage() ?: 'Unable to set active section.');
        }
    }

    /**
     * Resolve the active Section for the current authorized School.
     *
     * Supports session active_section_id and, when present, request header X-Section-Id.
     * Header values are validated strictly: an authorized active School is required, the
     * Section must exist, and it must belong to that exact School. Invalid or cross-School
     * identifiers never become active. Session and header must not establish contradictory
     * Section context for different Schools.
     *
     * @param  Request|null  $request  Optional request used to read X-Section-Id.
     * @return SchoolSection|null  Authorized Section, or null when none is valid.
     *
     * Preconditions: none (returns null when no authorized School is active).
     * Postconditions: when null is returned after an invalid context, active Section
     * session/in-memory state is cleared.
     * Side effects: may clear invalid Section context; may cache a valid Section.
     */
    public function getActiveSection(?Request $request = null): ?SchoolSection
    {
        try {
            $activeSchool = $this->getActiveSchool($request);

            // No authorized School means no authorized Section.
            if ($activeSchool === null) {
                $this->clearActiveSection();

                return null;
            }

            $headerId = $request?->header('X-Section-Id');
            $sessionId = session('active_section_id');

            // Prefer explicit header when provided; otherwise session; otherwise in-memory.
            $candidateId = $headerId ?: $sessionId;

            if ($this->activeSection && ! $candidateId) {
                if ((string) $this->activeSection->school_id !== (string) $activeSchool->id) {
                    $this->clearActiveSection();

                    return null;
                }

                return $this->activeSection;
            }

            if (! $candidateId) {
                return null;
            }

            $section = SchoolSection::find($candidateId);
            if (! $section || (string) $section->school_id !== (string) $activeSchool->id) {
                Log::warning('Rejected section context outside authorized school', [
                    'section_id' => $candidateId,
                    'active_school_id' => $activeSchool->id,
                    'source' => $headerId ? 'header' : 'session',
                ]);
                $this->clearActiveSection();

                return null;
            }

            // If header and session disagree, header wins only when valid; align session.
            $this->activeSection = $section;
            if (session('active_section_id') !== $section->id) {
                session(['active_section_id' => $section->id]);
            }

            return $section;
        } catch (\Exception $e) {
            Log::error('Failed to get active section: '.$e->getMessage());

            return null;
        }
    }
}
