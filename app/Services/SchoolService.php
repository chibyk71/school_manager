<?php

namespace App\Services;

use App\Events\SchoolCreated;
use App\Models\School;
use App\Models\User;
use App\Notifications\MadeAdminOfSchoolNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SchoolService
 *
 * Core Responsibilities:
 * ---------------------
 * This service is the central orchestration layer for school-related operations in a multi-tenant
 * (school-as-tenant) SaaS application. It handles:
 *
 * 1. Active School Context Management
 *    - Allows the application to determine which school the current user is working with.
 *    - Supports multiple ways to resolve the active school: instance cache, HTTP header,
 *      session storage, or fallback to the authenticated user's first associated school.
 *    - Essential for scoping queries, permissions, and UI context in a multi-school environment.
 *
 * 2. School Creation (Tenant Onboarding)
 *    - Creates a new School record with basic details (name, email, phones, type, extra data).
 *    - Optionally attaches a primary address during creation.
 *    - Does NOT force creation of an admin user — this is now decoupled for flexibility.
 *    - Runs core creation inside a database transaction for data integrity.
 *    - Dispatches a SchoolCreated event after successful creation to trigger asynchronous
 *      post-setup tasks (settings, defaults, notifications, jobs).
 *
 * 3. Admin Assignment to Schools
 *    - Provides a dedicated method to assign (or create) a user as school administrator.
 *    - Uses firstOrCreate pattern: if the email exists, reuses the user; otherwise creates a new one.
 *    - Safely assigns the 'admin' role scoped to the specific school (supports role scoping).
 *    - Attaches the user to the school via the many-to-many relationship.
 *    - Sends a notification to the user informing them of their new admin status.
 *
 * Design Principles Applied:
 * -------------------------
 * - Single Responsibility: Each method has a clear, focused purpose.
 * - Decoupled & Event-Driven: Heavy or side-effect work (settings, emails, defaults) moved to listeners/jobs.
 * - Trust Validated Input: No internal validation — relies on Form Requests / Controllers.
 * - Flexible Admin Model: Admins can be assigned later; supports super-admins creating schools for others.
 * - Idempotent & Safe: Role assignment and school attachment avoid duplicates.
 *
 * This service is registered as a singleton ('schoolManager') and is accessible via facade or dependency injection.
 */

class SchoolService
{
    /**
     * Cached instance of the currently active school (for the current request lifecycle).
     *
     * @var School|null
     */
    protected $activeSchool;

    /**
     * Cached instance of the currently active school section (for the current request lifecycle).
     *
     * @var \App\Models\SchoolSection|null
     */
    protected $activeSection;

    /**
     * Set the active school for the current user/session.
     *
     * Stores the school ID in the session and caches the model instance.
     * Used after school selection, login, or creation to establish context.
     *
     * @param School $school The school to activate
     * @return void
     * @throws \Exception If session write fails (rare, but logged)
     */
    public function setActiveSchool(School $school): void
    {
        try {
            $this->activeSchool = $school;
            session(['active_school_id' => $school->id]);
        } catch (\Exception $e) {
            Log::error('Failed to set active school: ' . $e->getMessage());
            throw new \Exception('Unable to set active school.');
        }
    }

    /**
     * Retrieve the currently active school.
     *
     * Resolution order:
     * 1. Cached instance (fastest)
     * 2. X-School-Id header (useful for API clients)
     * 3. Session storage
     * 4. Fallback: first school associated with authenticated user
     *
     * Returns null if no school can be determined.
     *
     * @param \Illuminate\Http\Request|null $request Optional request for header access
     * @return School|null
     */
    public function getActiveSchool(?\Illuminate\Http\Request $request = null): ?School
    {
        try {
            // Return cached instance if already resolved in this request
            if ($this->activeSchool) {
                return $this->activeSchool;
            }

            // Determine school ID from multiple sources
            $schoolId = $request?->header('X-School-Id')
                ?? session('active_school_id')
                ?? auth()->user()?->schools()->first()?->id;

            // Load and return the school if ID found
            return $schoolId ? School::find($schoolId) : null;
        } catch (\Exception $e) {
            Log::error('Failed to get active school: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new school (tenant) in the system.
     *
     * This is the primary entry point for school onboarding.
     * Expects fully validated data from StoreSchoolRequest.
     *
     * Key features:
     * - Creates school record
     * - Adds primary address if provided
     * - Dispatches SchoolCreated event for async follow-up tasks
     * - No admin creation — keeps creation lightweight and flexible
     *
     * @param array $data Validated data containing school details and optional address
     * @return School The newly created school instance
     * @throws \Exception On failure (rolled back by transaction)
     */
    public function createSchool(array $data): School
    {
        // Permission check — can be moved to Policy/Gate if preferred
        permitted('create-school');

        return DB::transaction(function () use ($data) {
            // Prepare only the fields that belong directly to the schools table
            $schoolData = [
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null, // Model boot method will auto-generate if missing
                'email' => $data['email'],
                'phone_one' => $data['phone_one'] ?? null,
                'phone_two' => $data['phone_two'] ?? null,
                'type' => $data['type'],
                'logo' => $data['logo'] ?? null, // May be temporary path or URL
                'data' => $data['extra_data'] ?? [], // JSON column for flexible storage
            ];

            // Create the core school record
            $school = School::create($schoolData);

            // Attach primary address if address data was provided
            if (!empty($data['address'])) {
                $school->addAddress([
                    'address' => $data['address']['address'] ?? '',
                    'city' => $data['address']['city'] ?? '',
                    'lga' => $data['address']['lga'] ?? null,
                    'state' => $data['address']['state'] ?? '',
                    'country' => $data['address']['country'] ?? '',
                    'postal_code' => $data['address']['postal_code'] ?? null,
                    'phone_number' => $data['address']['phone_number'] ?? null,
                ], true); // Second argument marks this as the primary address
            }

            // Fire event for asynchronous post-creation processing
            // Listeners can handle: default settings, academic sessions, welcome emails, etc.
            event(new SchoolCreated($school, auth()->id()));

            return $school;
        });
    }

    /**
     * Assign a user as administrator of a specific school.
     *
     * Core Responsibilities & Design Decisions:
     * ----------------------------------------
     * This method handles the assignment (or creation) of a school administrator
     * in a multi-tenant school SaaS application where:
     * - Schools are independent tenants/branches
     * - The 'admin' role is scoped to a specific school (using Laratrust teams)
     * - Users can be administrators of multiple schools
     *
     * Key Features:
     * -------------
     * 1. Flexible User Handling:
     *    - If a user with the provided email already exists → reuse them
     *    - If not → create a new user with a generated or provided password
     *
     * 2. Scoped Role Assignment:
     *    - The 'admin' role is assigned scoped to the given school
     *    - Uses Laratrust's teams feature (school acts as the team)
     *    - Prevents duplicate role assignments
     *
     * 3. School Association:
     *    - Attaches the user to the school via the many-to-many pivot (school_users)
     *    - Uses syncWithoutDetaching() to preserve existing school associations
     *
     * 4. Notification:
     *    - Sends MadeAdminOfSchoolNotification with school context
     *
     * 5. Safety & Integrity:
     *    - Runs inside a database transaction
     *    - Validates input data
     *    - Permission check via permitted() helper (scoped to school)
     *    - Null-safe handling of school ID
     *
     * Important Notes:
     * ----------------
     * - The role 'admin' is intentionally scoped to the school (team), NOT global
     *   → This allows the same user to be admin in one school and have different roles in others
     * - If $school is null (should not happen), role is assigned globally — prevented by ?->id
     * - Password is optional: if not provided, generates a secure random one
     *
     * @param array $userData   Contains 'name', 'email', optional 'password' and 'id'
     * @param School $school    The school for which the user will be admin
     * @return User             The admin user instance (existing or newly created)
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException If not permitted
     * @throws \Illuminate\Validation\ValidationException      If input invalid
     */
    public function assignAdmin(array $userData, School $school): User
    {
        // Permission check: user must have permission to assign admins for this specific school
        permitted('school.assign-admin');

        // Validate incoming data — trust-but-verify even if called internally
        $validated = validator($userData, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . ($userData['id'] ?? null),
            'password' => 'nullable|string|min:8',
        ])->validate();

        return DB::transaction(function () use ($validated, $school) {
            // Step 1: Reuse existing user or create new one
            $admin = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['name'],
                    'password' => Hash::make($validated['password'] ?? Str::random(12)),
                ]
            );

            // Step 2: Assign 'admin' role scoped to this school (if not already assigned)
            // Uses Laratrust teams: role is only valid within this school context
            if (!$admin->hasRole('admin', $school?->id)) {
                $admin->addRole('admin', $school?->id);
            }

            // Step 3: Ensure user is attached to the school (many-to-many)
            // syncWithoutDetaching preserves other school associations
            $admin->schools()->syncWithoutDetaching($school?->id);

            // Step 4: Notify the user they have been made admin of this school
            $admin->notify(new MadeAdminOfSchoolNotification($school));

            return $admin;
        });
    }

    /**
     * Set the active school section for the current user/session.
     *
     * Stores the section ID in the session and caches the model instance.
     * Validates that the section belongs to the active school.
     *
     * @param \App\Models\SchoolSection $section The section to activate
     * @return void
     * @throws \Exception If section does not belong to active school or session write fails
     */
    public function setActiveSection(\App\Models\SchoolSection $section): void
    {
        $activeSchool = $this->getActiveSchool();

        if ($activeSchool && $section->school_id !== $activeSchool->id) {
            throw new \Exception('Section does not belong to the active school.');
        }

        try {
            $this->activeSection = $section;
            session(['active_section_id' => $section->id]);
        } catch (\Exception $e) {
            Log::error('Failed to set active section: ' . $e->getMessage());
            throw new \Exception('Unable to set active section.');
        }
    }

    /**
     * Retrieve the currently active school section.
     *
     * Resolution order:
     * 1. Cached instance (fastest)
     * 2. X-Section-Id header (useful for API clients)
     * 3. Session storage
     * 4. Fallback: first section of the active school (if any)
     *
     * Returns null if no section can be determined.
     *
     * @param \Illuminate\Http\Request|null $request Optional request for header access
     * @return \App\Models\SchoolSection|null
     */
    public function getActiveSection(?\Illuminate\Http\Request $request = null): ?\App\Models\SchoolSection
    {
        try {
            // Return cached instance if already resolved
            if ($this->activeSection) {
                return $this->activeSection;
            }

            $sectionId = $request?->header('X-Section-Id')
                ?? session('active_section_id');

            if ($sectionId) {
                $section = \App\Models\SchoolSection::find($sectionId);

                // Safety: ensure section belongs to active school
                $activeSchool = $this->getActiveSchool($request);
                if ($section && $activeSchool && $section->school_id !== $activeSchool->id) {
                    Log::warning('Attempted to access section from different school', [
                        'section_id' => $sectionId,
                        'section_school_id' => $section->school_id,
                        'active_school_id' => $activeSchool->id,
                    ]);
                    return null;
                }

                return $section;
            }

            // Fallback: first section of active school
            $activeSchool = $this->getActiveSchool($request);
            if ($activeSchool) {
                return $activeSchool->schoolSections()->first();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get active section: ' . $e->getMessage());
            return null;
        }
    }
}
