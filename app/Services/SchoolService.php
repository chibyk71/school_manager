<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use App\Notifications\MadeAdminOfSchoolNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service class for managing schools in a single-tenant system.
 */
class SchoolService
{
    protected $activeSchool;

    /**
     * Set the active school and store its ID in the session.
     *
     * @param School $school The school to set as active.
     * @return void
     *
     * @throws \Exception If session storage fails.
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
     * Get the active school from the instance, request, or session.
     *
     * @param \Illuminate\Http\Request|null $request The incoming HTTP request.
     * @return School|null The active school model instance, or null if not found.
     *
     * @throws \Exception If school retrieval fails.
     */
    public function getActiveSchool(?\Illuminate\Http\Request $request = null): ?School
    {
        try {
            if ($this->activeSchool) {
                return $this->activeSchool;
            }

            $schoolId = $request?->header('X-School-Id') ?? session('active_school_id') ?? auth()->user()?->schools()->first()?->id;
            return $schoolId ? School::find($schoolId) : null;
        } catch (\Exception $e) {
            Log::error('Failed to get active school: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new school with an admin user and address.
     *
     * @param array $data The school, admin, and address data.
     * @return School The created school model instance.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If school creation or admin assignment fails.
     */
    public function createSchool(array $data): School
    {
        try {
            permitted('create-school');

            // Validate input data
            $validated = validator($data, [
                'name' => 'required|string|max:255',
                'slug' => 'nullable|string|max:255|unique:schools,slug',
                'email' => 'required|email|max:255|unique:schools,email',
                'phone_one' => 'nullable|string|max:20|regex:/^([0-9\s\-\+\(\)]*)$/',
                'phone_two' => 'nullable|string|max:20|regex:/^([0-9\s\-\+\(\)]*)$/',
                'type' => 'required|string|in:private,government,community',
                'logo' => 'nullable|string',
                'admin_name' => 'required|string|max:255',
                'admin_email' => 'required|email|max:255|unique:users,email',
                'admin_password' => 'required|string|min:8',
                'address' => 'required|array',
                'address.address' => 'required|string|max:255',
                'address.city' => 'required|string|max:255',
                'address.lga' => 'nullable|string|max:255',
                'address.state' => 'required|string|max:255',
                'address.country' => 'required|string|max:255',
                'address.postal_code' => 'nullable|string|max:20',
                'address.phone_number' => 'nullable|string|max:20',
                'extra_data' => 'nullable|array',
            ])->validate();

            return DB::transaction(function () use ($validated) {
                // Create the school
                $schoolData = [
                    'name' => $validated['name'],
                    'slug' => $validated['slug'] ?? null, // Slug will be generated in model boot
                    'email' => $validated['email'],
                    'phone_one' => $validated['phone_one'],
                    'phone_two' => $validated['phone_two'],
                    'type' => $validated['type'],
                    'logo' => $validated['logo'],
                    'data' => $validated['extra_data'] ?? [],
                ];

                $school = School::create($schoolData);

                // Create admin user
                $admin = User::create([
                    'name' => $validated['admin_name'],
                    'email' => $validated['admin_email'],
                    'password' => Hash::make($validated['admin_password']),
                ]);

                // Assign admin role and associate with school
                $admin->addRole('admin', $school->id);
                $admin->schools()->attach($school->id);
                $admin->notify(new MadeAdminOfSchoolNotification($school));

                // Create primary address
                $school->addAddress([
                    'address' => $validated['address']['address'],
                    'city' => $validated['address']['city'],
                    'lga' => $validated['address']['lga'],
                    'state' => $validated['address']['state'],
                    'country' => $validated['address']['country'],
                    'postal_code' => $validated['address']['postal_code'],
                    'phone_number' => $validated['address']['phone_number'],
                ], true);

                SaveOrUpdateSchoolSettings('sms', [
                    'sms_provider' => 'termii',
                    'sms_api_key' => null,
                    'sms_sender_id' => $validated['name'] ?? 'School',
                    'sms_enabled' => false,
                ], $school);
                SaveOrUpdateSchoolSettings('user_management', [
                    'online_admission' => false,
                    'allow_student_signin' => true,
                    'allow_parent_signin' => true,
                    'allow_teacher_signin' => true,
                    'allow_staff_signin' => true,
                    'online_admission_fee' => 0,
                    'online_admission_instruction' => null,
                ], $school);
                SaveOrUpdateSchoolSettings('gdpr', [
                    'content_text' => 'We use cookies to improve your experience.',
                    'position' => 'bottom',
                    'show_accept_button' => true,
                    'accept_button_text' => 'Accept',
                    'show_decline_button' => true,
                    'decline_button_text' => 'Decline',
                    'show_link' => true,
                    'link_text' => 'Learn More',
                    'link_url' => 'https://example.com/privacy',
                ], $school);
                SaveOrUpdateSchoolSettings('otp', [
                    'otp_type' => 'email',
                    'limit' => 6,
                    'eol' => 5,
                ], $school);
                SaveOrUpdateSchoolSettings('application', [
                    'app_name' => $school->name,
                    'short_name' => strtoupper(substr($school->name, 0, 2)),
                    'sidebar_default' => 'full',
                    'table_pagination' => 10,
                    'outside_click' => true,
                    'allow_school_custom_logo' => true,
                    'allow_school_default_payment' => false,
                ], $school);

                return $school;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create school: ' . $e->getMessage());
            throw new \Exception('Unable to create school: ' . $e->getMessage());
        }
    }

    /**
     * Assign an admin to a school.
     *
     * @param array $userData The admin user data.
     * @param School $school The school to assign the admin to.
     * @return User The created or updated admin user.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If admin assignment fails.
     */
    public function assignAdmin(array $userData, School $school): User
    {
        try {
            permitted('manage-school-admins');

            // Validate input data
            $validated = validator($userData, [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . ($userData['id'] ?? null),
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

                $admin->addRole('admin', $school->id);
                $admin->schools()->syncWithoutDetaching($school->id);
                $admin->notify(new MadeAdminOfSchoolNotification($school));

                return $admin;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to assign admin to school: ' . $e->getMessage());
            throw new \Exception('Unable to assign admin: ' . $e->getMessage());
        }
    }
}
