<?php

/**
 * SchoolService — School workspace orchestration within a Tenant deployment.
 *
 * A Tenant is the application/database instance. Multiple Schools share that Tenant.
 * This service manages School lifecycle operations and the authenticated user's active
 * School workspace context. It does not define Tenant identity.
 *
 * Workspace selection for authenticated users requires school_users membership.
 * Authentication itself does not require School membership.
 *
 * Section context is NOT owned by this service. School activation does not select,
 * validate, or clear Section state. Section Management / Academic workflows will
 * define Section context ownership later; coupling it to SchoolService was removed
 * deliberately so School workspace switching remains independent of unfinished
 * Section domain design.
 *
 * Create accepts nested addresses[] and persists via HasAddress (is_primary from payload only;
 * first address is NOT auto-primary). Update covers core school fields only; persisted address
 * mutations use the managed Address API.
 */

namespace App\Services;

use App\Enums\Auth\AccountLifecycle;
use App\Events\SchoolCreated;
use App\Models\Profile;
use App\Models\School;
use App\Models\User;
use App\Notifications\MadeAdminOfSchoolNotification;
use App\Services\School\SchoolLifecycleService;
use App\Services\UserManagement\UserAccountService;
use App\Support\Auth\SchoolWorkspaceAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SchoolService
{
    protected $activeSchool;

    /**
     * Bind an authorized School as the active workspace context.
     *
     * Purpose: Establish which School scopes subsequent School-workspace operations.
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
     *
     * Side effects: session key active_school_id when $persistSession is true.
     *
     * @throws \Exception When the authenticated user is not authorized to select $school,
     *                    or when session binding fails.
     */
    public function setActiveSchool(School $school, bool $persistSession = true): void
    {
        try {
            $user = auth()->user();
            if ($user !== null && ! SchoolWorkspaceAccess::canSelect($user, $school)) {
                throw new \Exception('You are not authorized to select this school workspace.');
            }

            $this->activeSchool = $school;
            if ($persistSession) {
                session(['active_school_id' => $school->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to set active school: '.$e->getMessage());
            throw new \Exception($e->getMessage() ?: 'Unable to set active school.');
        }
    }

    /**
     * Clear active School workspace context (in-memory and session).
     *
     * Section context is intentionally not managed here. Section activation belongs
     * to future Section Management / Academic workflows and must not be coupled to
     * School workspace selection.
     *
     * @return void
     *
     * Postconditions: activeSchool is null; session active_school_id is removed.
     */
    public function clearActiveSchool(): void
    {
        $this->activeSchool = null;
        session()->forget('active_school_id');
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
     * @return School|null Authorized School, or null when none is available/authorized.
     *
     * Side effects: may clear unauthorized session School context; may cache
     * the resolved School on $this->activeSchool.
     */
    public function getActiveSchool(?Request $request = null): ?School
    {
        try {
            if ($this->activeSchool) {
                $user = auth()->user();
                if ($user !== null && ! SchoolWorkspaceAccess::canSelect($user, $this->activeSchool)) {
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
                if ($school && $user && SchoolWorkspaceAccess::canSelect($user, $school)) {
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

    /**
     * Assign a School administrator for onboarding / school create flows.
     *
     * Invariant: the School contact email field is unrelated to administrator
     * identity. Callers must pass explicit administrator fields; controllers must
     * not treat generic "email" as an admin-assignment signal.
     *
     * Two supported paths (mutually exclusive):
     * 1. Existing Authentication Account: provide account_id (users.id). The account
     *    must already exist; its linked Person is used. No Person is created or
     *    matched by email.
     * 2. New administrator: provide first_name, last_name, email (Person identity)
     *    and optional password/username. A new Person is created, then an
     *    Authentication Account via UserAccountService. Email is never used to
     *    locate or reuse an existing Person.
     *
     * Side effects: school_users membership; Laratrust admin role on the school
     * team scope; MadeAdminOfSchoolNotification. Role assignment stays outside
     * UserAccountService.
     *
     * @param  array<string, mixed>  $userData
     * @return User The assigned administrator account
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function assignAdmin(array $userData, School $school): User
    {
        permitted('school.assign-admin');

        $hasExisting = ! empty($userData['account_id']) || ! empty($userData['id']);
        $hasNew = ! empty($userData['email']) || ! empty($userData['admin_email'])
            || ! empty($userData['first_name']) || ! empty($userData['admin_name']);

        if ($hasExisting && ($userData['email'] ?? $userData['admin_email'] ?? null)
            && empty($userData['account_id']) && empty($userData['id'])) {
            // Prefer explicit account_id when both shapes appear; do not invent matching.
        }

        if ($hasExisting) {
            $accountId = $userData['account_id'] ?? $userData['id'] ?? null;
            $validated = validator(
                ['account_id' => $accountId],
                ['account_id' => 'required|uuid|exists:users,id']
            )->validate();

            return DB::transaction(function () use ($validated, $school) {
                /** @var User $admin */
                $admin = User::query()->with('profile')->whereKey($validated['account_id'])->firstOrFail();

                if ($admin->profile === null || $admin->profile->trashed()) {
                    throw ValidationException::withMessages([
                        'account_id' => 'The selected account has no eligible Person.',
                    ]);
                }

                $admin->schools()->syncWithoutDetaching([$school->id]);

                // Pass the School model: Laratrust Helper::getIdFor() treats a plain
                // string as a model class name (where name = $string). UUID strings
                // therefore fail; the Eloquent instance supplies the team primary key.
                if (! $admin->hasRole('admin', $school)) {
                    $admin->addRole('admin', $school);
                }

                $admin->notify(new MadeAdminOfSchoolNotification($school));

                return $admin;
            });
        }

        // New administrator path — create Person then Authentication Account.
        $name = $userData['first_name'] ?? $userData['admin_name'] ?? $userData['name'] ?? null;
        $email = $userData['email'] ?? $userData['admin_email'] ?? null;
        $lastName = $userData['last_name'] ?? null;
        if ($lastName === null && is_string($name) && str_contains(trim($name), ' ')) {
            $parts = preg_split('/\s+/', trim($name), 2);
            $name = $parts[0] ?? $name;
            $lastName = $parts[1] ?? 'Administrator';
        }
        $lastName = $lastName ?: 'Administrator';

        $validated = validator([
            'first_name' => $name,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $userData['password'] ?? $userData['admin_password'] ?? null,
            'username' => $userData['username'] ?? null,
        ], [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|min:8',
            'username' => 'nullable|string|max:255',
        ])->validate();

        return DB::transaction(function () use ($validated, $school) {
            // Never reuse a Person because email matches — always create a new Person.
            $person = Profile::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => strtolower(trim($validated['email'])),
            ]);

            $accountService = app(UserAccountService::class);
            $plainPassword = $validated['password'] ?? null;
            $options = [
                'force_password_change' => true,
                'auto_generate_username' => empty($validated['username']),
                'send_notification' => filled($plainPassword) && filled($person->email),
            ];
            // Active only when usable credentials are supplied; otherwise Provisioned.
            if (filled($plainPassword)) {
                $options['lifecycle'] = AccountLifecycle::Active;
            } else {
                $options['lifecycle'] = AccountLifecycle::Provisioned;
            }

            $admin = $accountService->createForPerson(
                $person,
                [
                    'username' => $validated['username'] ?? null,
                    'password' => $plainPassword,
                ],
                $options
            );

            $admin->schools()->syncWithoutDetaching([$school->id]);

            // Pass the School model (not $school->id): see existing-account path note.
            if (! $admin->hasRole('admin', $school)) {
                $admin->addRole('admin', $school);
            }

            $admin->notify(new MadeAdminOfSchoolNotification($school));

            return $admin->load('profile');
        });
    }
}
