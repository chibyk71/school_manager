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
            ['name' => 'admissions_officer', 'display_name' => 'Admissions Officer', 'description' => 'Manages student admissions and enrollment processes'],
            ['name' => 'records_officer', 'display_name' => 'Records Officer', 'description' => 'Maintains student and institutional records'],
            ['name' => 'auditor', 'display_name' => 'Auditor', 'description' => 'Conducts internal audits of school processes and finances'],
            ['name' => 'hr_manager', 'display_name' => 'Human Resource Manager', 'description' => 'Oversees the human resources functions of the school'],
            ['name' => 'hr_officer', 'display_name' => 'Human Resource Officer', 'description' => 'Assists with recruitment, staff welfare, and HR administration'],
            ['name' => 'hr_assistant', 'display_name' => 'Human Resource Assistant', 'description' => 'Provides support in HR-related tasks and activities'],
        ],

        /**--------------------------------------------------------------
         *  FINANCE & ACCOUNTS
         * ------------------------------------------------------------ */
        "finance" => [
            ['name' => 'bursar', 'display_name' => 'Bursar', 'description' => 'Head of the Bursary/Accounts Department, responsible for managing school finances'],
            ['name' => 'accountant', 'display_name' => 'Accountant', 'description' => 'Responsible for financial record-keeping and reporting'],
            ['name' => 'assistant_accountant', 'display_name' => 'Assistant Accountant', 'description' => 'Assists the Accountant with financial tasks'],
            ['name' => 'accounts_clerk', 'display_name' => 'Accounts Clerk', 'description' => 'Provides clerical support to the Bursary/Accounts Department'],
        ],

        /* --------------------------------------------------------------
         *  ACADEMIC
         * ------------------------------------------------------------ */
        'academic' => [
            ['name' => 'teacher', 'display_name' => 'Teacher', 'description' => 'Handles subject teaching and class responsibilities'],
            ['name' => 'assistant-teacher', 'display_name' => 'Assistant Teacher', 'description' => 'Supports the lead teacher in classroom activities'],
            ['name' => 'hod', 'display_name' => 'Head of Department (HOD)', 'description' => 'Leads a subject department and coordinates teachers'],
            ['name' => 'subject-coordinator', 'display_name' => 'Subject Coordinator', 'description' => 'Coordinates subject implementation across multiple classes'],
            ['name' => 'class-teacher', 'display_name' => 'Class Teacher', 'description' => 'In charge of a specific class, including academic and behavioral monitoring'],
            ['name' => 'exam-officer', 'display_name' => 'Exam Officer', 'description' => 'Manages school examination logistics and records'],
            ['name' => 'examinations_officer', 'display_name' => 'Examinations Officer', 'description' => 'Responsible for organizing and managing school examinations'],
            ['name' => 'lab-assistant', 'display_name' => 'Lab Assistant', 'description' => 'Supports science experiments and maintains laboratory materials'],
            ['name' => 'patron_club_society', 'display_name' => 'Patron/Matron of Club/Society', 'description' => 'Teacher assigned to oversee and guide a specific student club or society'],
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
            ['name' => 'head_student_affairs', 'display_name' => 'Head of Student Affairs', 'description' => 'Oversees student welfare, discipline, and co-curricular life'],
            ['name' => 'welfare_officer', 'display_name' => 'Welfare Officer', 'description' => 'Supports student welfare and pastoral care'],
            ['name' => 'discipline_master', 'display_name' => 'Discipline Master', 'description' => 'Enforces school rules and manages disciplinary processes'],
        ],

        /* --------------------------------------------------------------
         *  HOSTEL / BOARDING
         * ------------------------------------------------------------ */
        'hostel' => [
            ['name' => 'boarding_house_master', 'display_name' => 'Boarding House Master', 'description' => 'Manages a boarding house'],
            ['name' => 'assistant_boarding_master', 'display_name' => 'Assistant Boarding House Master', 'description' => 'Assists with boarding house management'],
            ['name' => 'warden', 'display_name' => 'Warden', 'description' => 'Oversees boarding students'],
            ['name' => 'matron', 'display_name' => 'Matron', 'description' => 'Oversees boarding welfare'],
            ['name' => 'caretaker_boarding', 'display_name' => 'Boarding Caretaker', 'description' => 'Supports boarding house operations'],
        ],

        /* --------------------------------------------------------------
         *  CLINIC / HEALTH
         * ------------------------------------------------------------ */
        'clinic' => [
            ['name' => 'head_clinic', 'display_name' => 'Head of Clinic', 'description' => 'Leads the school clinic'],
            ['name' => 'nurse', 'display_name' => 'Nurse', 'description' => 'Provides health care to students'],
            ['name' => 'clinic_attendant', 'display_name' => 'Clinic Attendant', 'description' => 'Supports clinic operations'],
        ],

        /* --------------------------------------------------------------
         *  SECURITY
         * ------------------------------------------------------------ */
        'security' => [
            ['name' => 'head_security', 'display_name' => 'Head of Security', 'description' => 'Leads school security'],
            ['name' => 'security_officer', 'display_name' => 'Security Officer', 'description' => 'Provides security on school premises'],
            ['name' => 'security_guard', 'display_name' => 'Security Guard', 'description' => 'Guards school premises'],
        ],

        /* --------------------------------------------------------------
         *  MAINTENANCE
         * ------------------------------------------------------------ */
        'maintenance' => [
            ['name' => 'head_maintenance', 'display_name' => 'Head of Maintenance', 'description' => 'Leads facilities maintenance'],
            ['name' => 'electrician', 'display_name' => 'Electrician', 'description' => 'Handles electrical maintenance'],
            ['name' => 'groundskeeper', 'display_name' => 'Groundskeeper', 'description' => 'Maintains school grounds'],
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
