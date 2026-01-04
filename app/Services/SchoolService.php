<?php

/**
 * SchoolService v3.0 – Production-Ready with Polymorphic Address Integration
 *
 * Purpose & Context:
 * ------------------
 * Central orchestration service for all school (tenant) operations in the multi-tenant SaaS.
 * Updated to fully integrate with the polymorphic HasAddress trait on the School model.
 *
 * Key Changes & Improvements in v3.0:
 * -----------------------------------
 * - createSchool() and updateSchool() now handle primary address via HasAddress trait methods:
 *   • Uses $school->addAddress($data, true) on create
 *   • Uses intelligent primary address upsert on update (update existing if present, add new if not)
 * - Address payload standardized to 'primary_address' (flattened array) to match Store/UpdateSchoolRequest
 *   and upcoming CreateEdit.vue form.
 * - Removed outdated nested 'address' handling (old JSON-style fields).
 * - Validation fully delegated to HasAddress::validateAddressData() – no duplication here.
 * - Media handling left untouched (Spatie collections managed in controller via $request->file()).
 * - Transaction boundaries preserved for data integrity.
 * - Comprehensive logging and error handling.
 * - Event dispatching unchanged (SchoolCreated still fires for async onboarding).
 *
 * Problems Solved:
 * ----------------
 * - Eliminates address validation/logic duplication across requests, services, and models.
 * - Ensures consistent primary address behavior (only one primary per school).
 * - Supports partial address updates without losing existing data.
 * - Aligns perfectly with frontend types (address.ts) and form structure.
 * - Maintains multi-tenant safety: HasAddress automatically assigns current school_id.
 *
 * Usage Flow (Create):
 * -------------------
 * 1. StoreSchoolRequest validates core fields + optional primary_address array.
 * 2. Controller calls $this->schoolService->createSchool($validated).
 * 3. Service creates school → adds primary address if provided → fires SchoolCreated event.
 *
 * Usage Flow (Update):
 * -------------------
 * 1. UpdateSchoolRequest validates core + optional primary_address.
 * 2. Controller calls $this->schoolService->updateSchool($school, $validated).
 * 3. Service updates core attributes → upserts primary address if provided.
 *
 * Fits into School Module:
 * ------------------------
 * Works with SchoolController (create/edit/store/update), Store/UpdateSchoolRequest,
 * HasAddress trait, and the upcoming combined CreateEdit.vue page.
 * Future-proof for AddressService integration (events, notifications, geocoding).
 */

namespace App\Services;

use App\Events\SchoolCreated;
use App\Models\School;
use App\Models\User;
use App\Notifications\MadeAdminOfSchoolNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SchoolService
{
    protected $activeSchool;
    protected $activeSection;

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

    public function getActiveSchool(?\Illuminate\Http\Request $request = null): ?School
    {
        try {
            if ($this->activeSchool) {
                return $this->activeSchool;
            }

            $schoolId = $request?->header('X-School-Id')
                ?? session('active_school_id')
                ?? auth()->user()?->schools()->first()?->id;

            return $schoolId ? School::find($schoolId) : null;
        } catch (\Exception $e) {
            Log::error('Failed to get active school: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new school with optional primary address.
     *
     * @param array $data Validated data from StoreSchoolRequest (includes optional 'primary_address')
     * @return School
     */
    public function createSchool(array $data): School
    {
        // Permission handled in controller/policy
        return DB::transaction(function () use ($data) {
            $schoolData = [
                'name'       => $data['name'],
                'code'       => $data['code'] ?? null,
                'email'      => $data['email'],
                'phone_one'  => $data['phone_one'] ?? null,
                'phone_two'  => $data['phone_two'] ?? null,
                'type'       => $data['type'],
                'is_active'  => $data['is_active'] ?? true,
                'data'       => $data['extra_data'] ?? [],
            ];

            $school = School::create($schoolData);

            // Handle primary address if provided
            if (!empty($data['primary_address'])) {
                $school->addAddress($data['addresses'], true);
            }

            event(new SchoolCreated($school, auth()->id()));

            Log::info('School created successfully', ['school_id' => $school->id]);

            return $school;
        });
    }

    /**
     * Update an existing school with optional primary address changes.
     *
     * @param School $school
     * @param array $data Validated data from UpdateSchoolRequest
     * @return School
     */
    public function updateSchool(School $school, array $data): School
    {
        return DB::transaction(function () use ($school, $data) {
            $school->fill([
                'name'       => $data['name'] ?? $school->name,
                'code'       => $data['code'] ?? $school->code,
                'email'      => $data['email'] ?? $school->email,
                'phone_one'  => $data['phone_one'] ?? $school->phone_one,
                'phone_two'  => $data['phone_two'] ?? $school->phone_two,
                'type'       => $data['type'] ?? $school->type,
                'is_active'  => $data['is_active'] ?? $school->is_active,
                'data'       => array_merge($school->data ?? [], $data['extra_data'] ?? []),
            ]);

            $school->save();

            // Upsert primary address if provided
            if (array_key_exists('primary_address', $data)) {
                $primary = $school->primaryAddress();

                if ($primary && !empty($data['primary_address'])) {
                    // Update existing primary address
                    $school->updateAddress($primary->id, $data['primary_address']);
                } elseif (!empty($data['primary_address'])) {
                    // No primary exists → create new one
                    $school->addAddress($data['primary_address'], true);
                } elseif ($primary && empty($data['primary_address'])) {
                    // Optional: soft-delete primary if payload explicitly empty? (not recommended)
                    // Currently: do nothing – keeps existing primary
                }
            }

            Log::info('School updated successfully', ['school_id' => $school->id]);

            return $school;
        });
    }

    public function assignAdmin(array $userData, School $school): User
    {
        permitted('school.assign-admin');

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

            if (!$admin->hasRole('admin', $school?->id)) {
                $admin->addRole('admin', $school?->id);
            }

            $admin->schools()->syncWithoutDetaching($school?->id);

            $admin->notify(new MadeAdminOfSchoolNotification($school));

            return $admin;
        });
    }

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

    public function getActiveSection(?\Illuminate\Http\Request $request = null): ?\App\Models\SchoolSection
    {
        try {
            if ($this->activeSection) {
                return $this->activeSection;
            }

            $sectionId = $request?->header('X-Section-Id')
                ?? session('active_section_id');

            if ($sectionId) {
                $section = \App\Models\SchoolSection::find($sectionId);

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

            $activeSchool = $this->getActiveSchool($request);
            return $activeSchool?->schoolSections()->first();
        } catch (\Exception $e) {
            Log::error('Failed to get active section: ' . $e->getMessage());
            return null;
        }
    }
}
