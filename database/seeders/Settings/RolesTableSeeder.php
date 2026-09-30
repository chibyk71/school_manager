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
            ['name' => 'dean_of_studies', 'display_name' => 'Dean of Studies', 'description' => 'Oversees academic programs and curriculum'],
            ['name' => 'head_of_department', 'display_name' => 'Head of Department', 'description' => 'Leads a subject department'],
            ['name' => 'subject_teacher', 'display_name' => 'Subject Teacher', 'description' => 'Teaches one or more subjects'],
            ['name' => 'class_teacher', 'display_name' => 'Class Teacher', 'description' => 'Responsible for a class'],
            ['name' => 'form_tutor', 'display_name' => 'Form Tutor', 'description' => 'Pastoral care for a form group'],
            ['name' => 'teacher', 'display_name' => 'Teacher', 'description' => 'General teaching staff'],
            ['name' => 'lab_attendant', 'display_name' => 'Laboratory Attendant', 'description' => 'Supports science laboratory activities'],
            ['name' => 'exam_officer', 'display_name' => 'Examination Officer', 'description' => 'Coordinates internal and external examinations'],
            ['name' => 'curriculum_coordinator', 'display_name' => 'Curriculum Coordinator', 'description' => 'Coordinates curriculum planning and delivery'],
        ],

        /* --------------------------------------------------------------
         *  GUIDANCE & COUNSELING
         * ------------------------------------------------------------ */
        'guidance_counseling' => [
            ['name' => 'head_guidance_counseling', 'display_name' => 'Head of Guidance and Counseling Unit', 'description' => "Leads the school's guidance and counseling services"],
            ['name' => 'counselor', 'display_name' => 'Counselor', 'description' => 'Provides guidance and counseling services to students'],
        ],

        /* --------------------------------------------------------------
         *  LIBRARY
         * ------------------------------------------------------------ */
        'library' => [
            ['name' => 'librarian', 'display_name' => 'Librarian', 'description' => 'Manages the school library and resources'],
            ['name' => 'assistant_librarian', 'display_name' => 'Assistant Librarian', 'description' => 'Assists the School Librarian with library duties'],
            ['name' => 'library_assistant', 'display_name' => 'Library Assistant', 'description' => 'Provides support in the school library'],
        ],

        /* --------------------------------------------------------------
         *  ICT / MIS
         * ------------------------------------------------------------ */
        'ict' => [
            ['name' => 'head_ict_mis', 'display_name' => 'Head of ICT/MIS Department', 'description' => "Leads the school's Information and Communication Technology and Management Information Systems"],
            ['name' => 'ict_officer', 'display_name' => 'ICT Officer', 'description' => "Responsible for managing and maintaining the school's ICT infrastructure"],
            ['name' => 'systems_administrator', 'display_name' => 'Systems Administrator', 'description' => "Responsible for the school's computer systems and networks"],
            ['name' => 'it_technician', 'display_name' => 'IT Technician', 'description' => 'Provides technical support for the school\'s IT equipment'],
            ['name' => 'it-support', 'display_name' => 'IT Support', 'description' => 'Manages and supports the school management software and tech systems'],
        ],

        /* --------------------------------------------------------------
         *  STUDENT WELFARE / PASTORAL
         * ------------------------------------------------------------ */
        'welfare' => [
            ['name' => 'dean_of_students', 'display_name' => 'Dean of Students', 'description' => 'Oversees student welfare and discipline'],
            ['name' => 'housemaster', 'display_name' => 'Housemaster', 'description' => 'Responsible for a student house'],
            ['name' => 'housemistress', 'display_name' => 'Housemistress', 'description' => 'Responsible for a student house'],
            ['name' => 'prefect', 'display_name' => 'Prefect', 'description' => 'Student leadership role'],
        ],

        /* --------------------------------------------------------------
         *  HOSTEL
         * ------------------------------------------------------------ */
        'hostel' => [
            ['name' => 'hostel_warden', 'display_name' => 'Hostel Warden', 'description' => 'Manages student hostel accommodation'],
            ['name' => 'assistant_warden', 'display_name' => 'Assistant Warden', 'description' => 'Assists the Hostel Warden'],
        ],

        /* --------------------------------------------------------------
         *  CLINIC / HEALTH
         * ------------------------------------------------------------ */
        'clinic' => [
            ['name' => 'school_nurse', 'display_name' => 'School Nurse', 'description' => 'Provides health services to students'],
            ['name' => 'matron', 'display_name' => 'Matron', 'description' => 'Oversees student health and welfare'],
        ],

        /* --------------------------------------------------------------
         *  SECURITY
         * ------------------------------------------------------------ */
        'security' => [
            ['name' => 'chief_security_officer', 'display_name' => 'Chief Security Officer', 'description' => 'Leads school security'],
            ['name' => 'security_guard', 'display_name' => 'Security Guard', 'description' => 'Provides security on school premises'],
        ],

        /* --------------------------------------------------------------
         *  MAINTENANCE
         * ------------------------------------------------------------ */
        'maintenance' => [
            ['name' => 'estate_manager', 'display_name' => 'Estate Manager', 'description' => 'Manages school facilities and maintenance'],
            ['name' => 'maintenance_officer', 'display_name' => 'Maintenance Officer', 'description' => 'Handles facility maintenance'],
        ],

        /* --------------------------------------------------------------
         *  TRANSPORT
         * ------------------------------------------------------------ */
        'transport' => [
            ['name' => 'transport_officer', 'display_name' => 'Transport Officer', 'description' => 'Responsible for managing school transportation'],
            ['name' => 'driver', 'display_name' => 'Driver', 'description' => 'Responsible for driving school vehicles'],
            ['name' => 'school_driver', 'display_name' => 'School Driver', 'description' => 'Responsible for driving school vehicles (if applicable)'],
        ],

        /* --------------------------------------------------------------
         *  KITCHEN / CATERING
         * ------------------------------------------------------------ */
        'kitchen' => [
            ['name' => 'catering_manager', 'display_name' => 'Catering Manager', 'description' => "Responsible for managing the school's catering services"],
            ['name' => 'cook', 'display_name' => 'Cook', 'description' => 'Responsible for preparing meals in the school cafeteria (if applicable)'],
            ['name' => 'kitchen_staff', 'display_name' => 'Kitchen Staff', 'description' => 'Assists with food preparation and kitchen duties'],
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
