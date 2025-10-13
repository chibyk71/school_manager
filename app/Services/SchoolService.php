<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use App\Notifications\MadeAdminOfSchoolNotification;
use Illuminate\Support\Facades\Hash;

class SchoolService
{
    protected $activeSchool;

    public function setActiveSchool($school)
    {
        $this->activeSchool = $school;
        session(['active_school_id' => $school->id]);
    }

    public function getActiveSchool()
    {
        return $this->activeSchool ?? $this->getSchoolFromSession();
    }

    protected function getSchoolFromSession()
    {
        $schoolId = session('active_school_id');
        return $schoolId ? School::findOrFail($schoolId) : null;
    }

    public function createSchool(array $data): School
    {
        // Validate required fields
        if (empty($data['name']) || empty($data['email']) || empty($data['admin_name']) || empty($data['admin_password'])) {
            throw new \InvalidArgumentException('Missing required fields for school creation.');
        }

        // Start a transaction for atomicity
        return \DB::transaction(function () use ($data) {
            // Create the school
            $school = School::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone_one' => $data['phone_one'] ?? null,
                'phone_two' => $data['phone_two'] ?? null,
                'tenancy_type' => $data['tenancy_type'] ?? 'private',
                'parent_id' => $data['parent_id'] ?? null,
                'data' => $data['extra_data'] ?? [],
            ]);

            // Check if admin user exists
            $admin = User::where('email', $data['email'])->first();

            if ($admin) {
                // Assign admin role to existing user
                $admin->assignRole('admin', $school->id);
                $admin->notify(new MadeAdminOfSchoolNotification($school));
                throw new \Exception("User with email {$data['email']} already exists and has been assigned as admin.", 409);
            }

            // Create new admin user
            $admin = User::create([
                'name' => $data['admin_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['admin_password']),
            ]);

            // Assign admin role and associate with school
            $admin->assignRole('admin', $school->id);
            $admin->schools()->attach($school->id);
            $admin->notify(new MadeAdminOfSchoolNotification($school));

            return $school;
        });
    }

    public function assignAdmin(array $userData, School $school): User
    {
        $admin = User::firstOrCreate(
            ['email' => $userData['email']],
            [
                'name' => $userData['name'],
                'password' => Hash::make($userData['password'] ?? Str::random(12)),
            ]
        );

        $admin->assignRole('admin', $school->id);
        $admin->schools()->attach($school->id);
        $admin->notify(new MadeAdminOfSchoolNotification($school));

        return $admin;
    }
}
