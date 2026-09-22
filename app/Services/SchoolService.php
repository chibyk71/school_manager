<?php

/**
 * SchoolService — School (tenant) orchestration with Phase 4 Address integration.
 *
 * Create: accepts nested addresses[] and persists via HasAddress (is_primary from payload only;
 * first address is NOT auto-primary).
 * Update: core school fields only; persisted address mutations use the managed Address API.
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
     * Create a new school with optional nested addresses[].
     *
     * @param  array<string, mixed>  $data  Validated data from StoreSchoolRequest
     */
    public function createSchool(array $data): School
    {
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

            // Nested addresses[] on create (embedded mode). First address is NOT auto-primary;
            // is_primary comes from submitted payload only.
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

            event(new SchoolCreated($school, auth()->id()));

            Log::info('School created successfully', ['school_id' => $school->id]);

            return $school;
        });
    }

    /**
     * Update core school attributes only.
     * Persisted address mutations are handled by the managed Address API (Phase 4).
     *
     * @param  array<string, mixed>  $data
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

            // Persisted address mutations are handled by the managed Address API (Phase 4).
            // School update no longer upserts primary_address from the school form.

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

            if (! $admin->hasRole('admin', $school?->id)) {
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
