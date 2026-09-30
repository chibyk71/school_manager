<?php
// database/seeders/RoleAndDepartmentRoleSeeder.php

namespace Database\Seeders\Settings;

use App\Models\Employee\Department;
use App\Models\Employee\DepartmentRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    /** @var array<string, array<int, array{name:string,display_name:string,description:string}>> */
    protected array $rolesByDepartment = [

        /* --------------------------------------------------------------
         *  ADMINISTRATION
         * ------------------------------------------------------------ */
        'administration' => [
            ['name' => 'principal', 'display_name' => 'Principal', 'description' => 'The head of the school, responsible for overall administration and academic leadership'],
            ['name' => 'vice_principal_academic', 'display_name' => 'Vice Principal (Academic)', 'description' => 'Assists the Principal with academic matters'],
            ['name' => 'vice_principal_admin', 'display_name' => 'Vice Principal (Administration)', 'description' => 'Assists the Principal with administrative matters'],
            ['name' => 'school_secretary', 'display_name' => 'School Secretary', 'description' => "Responsible for administrative tasks and record-keeping in the Principal's office"],
            ['name' => 'assistant_secretary', 'display_name' => 'Assistant Secretary', 'description' => 'Assists the School Secretary with administrative tasks'],
            ['name' => 'administrative_officer', 'display_name' => 'Administrative Officer', 'description' => 'Handles various administrative duties within the school'],
            ['name' => 'pa_to_principal', 'display_name' => 'Personal Assistant to the Principal', 'description' => 'Provides administrative and secretarial support to the Principal'],
            ['name' => 'public_relations_officer', 'display_name' => 'Public Relations Officer', 'description' => "Responsible for managing the school's public image and communication"],
            ['name' => 'information_officer', 'display_name' => 'Information Officer', 'description' => 'Responsible for disseminating information within and outside the school'],
            ['name' => 'admin', 'display_name' => 'Super Admin', 'description' => 'Has full system access across all schools and modules'],
            ['name' => 'school-owner', 'display_name' => 'School Owner', 'description' => 'Owner or proprietor of the school with administrative oversight'],
        ],

        /* --------------------------------------------------------------
         *  ACADEMIC
         * ------------------------------------------------------------ */
        'academic' => [
            ['name' => 'head_of_department', 'display_name' => 'Head of Department', 'description' => 'Leads an academic department'],
            ['name' => 'subject_teacher', 'display_name' => 'Subject Teacher', 'description' => 'Teaches specific subjects'],
            ['name' => 'class_teacher', 'display_name' => 'Class Teacher', 'description' => 'Responsible for a class'],
            ['name' => 'teacher', 'display_name' => 'Teacher', 'description' => 'General teaching staff'],
        ],

        /* --------------------------------------------------------------
         *  FINANCE
         * ------------------------------------------------------------ */
        'finance' => [
            ['name' => 'bursar', 'display_name' => 'Bursar', 'description' => 'Manages school finances'],
            ['name' => 'accountant', 'display_name' => 'Accountant', 'description' => 'Handles accounting duties'],
        ],

        /* --------------------------------------------------------------
         *  LIBRARY
         * ------------------------------------------------------------ */
        'library' => [
            ['name' => 'librarian', 'display_name' => 'Librarian', 'description' => 'Manages the school library and resources'],
            ['name' => 'assistant_librarian', 'display_name' => 'Assistant Librarian', 'description' => 'Assists the School Librarian with library duties'],
        ],

        /* --------------------------------------------------------------
         *  PARENT / GUARDIAN (virtual)
         * ------------------------------------------------------------ */
        'parent' => [
            ['name' => 'parent', 'display_name' => 'Parent', 'description' => 'Parent of a student'],
            ['name' => 'guardian', 'display_name' => 'Guardian', 'description' => 'Legal guardian of a student'],
        ],

        /* --------------------------------------------------------------
         *  STUDENT (virtual)
         * ------------------------------------------------------------ */
        'student' => [
            ['name' => 'student', 'display_name' => 'Student', 'description' => 'Enrolled student of the school']
        ],
    ];

    public function run(): void
    {
        // Phase 2: seed tenant/global role definitions (school_id = NULL).
        // School-local roles are created later via role management / Phase 3 customization.
        // Department linking remains for HRM presentation; departments may be school-scoped.
        $school = \App\Models\School::first();

        foreach ($this->rolesByDepartment as $category => $roleList) {

            // --------------------------------------------------------------
            // 1. Resolve the Department record
            // --------------------------------------------------------------
            $department = Department::where('category', $category)
                ->orWhere('name', 'LIKE', "%{$category}%")
                ->first();

            if (!$department) {
                // Create a fallback department (system-level)
                $department = Department::create([
                    'school_id' => $school?->id ?? null,
                    'name' => ucfirst(str_replace('_', ' ', $category)),
                    'category' => $category,
                    'effective_date' => now(),
                ]);
            }

            // --------------------------------------------------------------
            // 2. Create each Role as a tenant/global definition
            // --------------------------------------------------------------
            foreach ($roleList as $data) {
                $role = Role::updateOrCreate(
                    [
                        'name' => $data['name'],
                        'school_id' => null,
                    ],
                    [
                        'display_name' => $data['display_name'],
                        'description' => $data['description'],
                        'disabled' => false,
                    ]
                );

                // --------------------------------------------------------------
                // 3. Link Role → Department (department_role pivot)
                // --------------------------------------------------------------
                if (!$role->departments()->where('department_id', $department->id)->exists()) {
                    $role->departments()->attach($department->id);
                }
            }
        }
    }
}
