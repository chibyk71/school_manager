<?php

namespace Database\Seeders\Settings;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // Truncate the roles table
        Role::truncate();
        Db::statement('SET FOREIGN_KEY_CHECKS=1;');
        // Truncate the role_user table
        DB::table('role_user')->truncate();
        // Truncate the permission_role table
        DB::table('permission_role')->truncate();


        $roles = [
            [
                'name' => 'teacher',
                'display_name' => 'Teacher',
                'description' => 'Handles subject teaching and class responsibilities',
            ],
            [
                'name' => 'assistant-teacher',
                'display_name' => 'Assistant Teacher',
                'description' => 'Supports the lead teacher in classroom activities',
            ],
            [
                'name' => 'hod',
                'display_name' => 'Head of Department (HOD)',
                'description' => 'Leads a subject department and coordinates teachers',
            ],
            [
                'name' => 'subject-coordinator',
                'display_name' => 'Subject Coordinator',
                'description' => 'Coordinates subject implementation across multiple classes',
            ],
            [
                'name' => 'class-teacher',
                'display_name' => 'Class Teacher',
                'description' => 'In charge of a specific class, including academic and behavioral monitoring',
            ],
            [
                'name' => 'lesson-planner',
                'display_name' => 'Lesson Planner',
                'description' => 'Responsible for preparing curriculum schedules and lesson plans',
            ],
            [
                'name' => 'exam-officer',
                'display_name' => 'Exam Officer',
                'description' => 'Manages school examination logistics and records',
            ],
            [
                'name' => 'librarian',
                'display_name' => 'Librarian',
                'description' => 'Manages the school library and resources',
            ],
            [
                'name' => 'lab-assistant',
                'display_name' => 'Lab Assistant',
                'description' => 'Supports science experiments and maintains laboratory materials',
            ],
            [
                'name' => 'principal',
                'display_name' => 'Principal',
                'description' => 'The head of the school, responsible for overall administration and academic leadership',
            ],
            [
                'name' => 'vice_principal_academic',
                'display_name' => 'Vice Principal (Academic)',
                'description' => 'Assists the Principal with academic matters',
            ],
            [
                'name' => 'vice_principal_admin',
                'display_name' => 'Vice Principal (Administration)',
                'description' => 'Assists the Principal with administrative matters',
            ],
            [
                'name' => 'school_secretary',
                'display_name' => 'School Secretary',
                'description' => 'Responsible for administrative tasks and record-keeping in the Principal\'s office',
            ],
            [
                'name' => 'assistant_secretary',
                'display_name' => 'Assistant Secretary',
                'description' => 'Assists the School Secretary with administrative tasks',
            ],
            [
                'name' => 'administrative_officer',
                'display_name' => 'Administrative Officer',
                'description' => 'Handles various administrative duties within the school',
            ],
            [
                'name' => 'records_officer',
                'display_name' => 'Records Officer',
                'description' => 'Responsible for managing and maintaining school records',
            ],
            [
                'name' => 'pa_to_principal',
                'display_name' => 'Personal Assistant to the Principal',
                'description' => 'Provides administrative and secretarial support to the Principal',
            ],
            [
                'name' => 'bursar',
                'display_name' => 'Bursar',
                'description' => 'Head of the Bursary/Accounts Department, responsible for managing school finances',
            ],
            [
                'name' => 'accountant',
                'display_name' => 'Accountant',
                'description' => 'Responsible for financial record-keeping and reporting',
            ],
            [
                'name' => 'assistant_accountant',
                'display_name' => 'Assistant Accountant',
                'description' => 'Assists the Accountant with financial tasks',
            ],
            [
                'name' => 'accounts_clerk',
                'display_name' => 'Accounts Clerk',
                'description' => 'Provides clerical support to the Bursary/Accounts Department',
            ],
            [
                'name' => 'head_guidance_counseling',
                'display_name' => 'Head of Guidance and Counseling Unit',
                'description' => 'Leads the school\'s guidance and counseling services',
            ],
            [
                'name' => 'counselor',
                'display_name' => 'Counselor',
                'description' => 'Provides guidance and counseling services to students',
            ],
            [
                'name' => 'examinations_officer',
                'display_name' => 'Examinations Officer',
                'description' => 'Responsible for organizing and managing school examinations',
            ],
            [
                'name' => 'assistant_librarian',
                'display_name' => 'Assistant Librarian',
                'description' => 'Assists the School Librarian with library duties',
            ],
            [
                'name' => 'library_assistant',
                'display_name' => 'Library Assistant',
                'description' => 'Provides support in the school library',
            ],
            [
                'name' => 'head_ict_mis',
                'display_name' => 'Head of ICT/MIS Department',
                'description' => 'Leads the school\'s Information and Communication Technology and Management Information Systems',
            ],
            [
                'name' => 'ict_officer',
                'display_name' => 'ICT Officer',
                'description' => 'Responsible for managing and maintaining the school\'s ICT infrastructure',
            ],
            [
                'name' => 'systems_administrator',
                'display_name' => 'Systems Administrator',
                'description' => 'Responsible for the school\'s computer systems and networks',
            ],
            [
                'name' => 'it_technician',
                'display_name' => 'IT Technician',
                'description' => 'Provides technical support for the school\'s IT equipment',
            ],
            [
                'name' => 'head_student_affairs',
                'display_name' => 'Head of Student Affairs/Welfare',
                'description' => 'Leads the department responsible for student welfare and discipline',
            ],
            [
                'name' => 'welfare_officer',
                'display_name' => 'Welfare Officer',
                'description' => 'Responsible for the well-being and welfare of students',
            ],
            [
                'name' => 'discipline_master',
                'display_name' => 'Discipline Master/Mistress',
                'description' => 'Responsible for maintaining student discipline',
            ],
            [
                'name' => 'boarding_house_master',
                'display_name' => 'Boarding House Master/Mistress',
                'description' => 'Responsible for the management and supervision of the boarding house',
            ],
            [
                'name' => 'assistant_boarding_master',
                'display_name' => 'Assistant Boarding House Master/Mistress',
                'description' => 'Assists the Boarding House Master/Mistress',
            ],
            [
                'name' => 'warden',
                'display_name' => 'Warden',
                'description' => 'Supervises students within the boarding house',
            ],
            [
                'name' => 'matron',
                'display_name' => 'Matron',
                'description' => 'Responsible for the care and well-being of female students in the boarding house',
            ],
            [
                'name' => 'caretaker_boarding',
                'display_name' => 'Boarding House Caretaker',
                'description' => 'Provides general support and maintenance in the boarding house',
            ],
            [
                'name' => 'head_security',
                'display_name' => 'Head of Security',
                'description' => 'Responsible for the overall security of the school premises',
            ],
            [
                'name' => 'security_officer',
                'display_name' => 'Security Officer',
                'description' => 'Responsible for maintaining security and order within the school',
            ],
            [
                'name' => 'security_guard',
                'display_name' => 'Security Guard',
                'description' => 'Patrols the school premises and ensures security',
            ],
            [
                'name' => 'head_maintenance',
                'display_name' => 'Head of Maintenance Department',
                'description' => 'Responsible for overseeing the maintenance of school facilities',
            ],
            [
                'name' => 'electrician',
                'display_name' => 'Electrician',
                'description' => 'Responsible for electrical repairs and maintenance',
            ],
            [
                'name' => 'groundskeeper',
                'display_name' => 'Groundskeeper',
                'description' => 'Responsible for maintaining the school grounds',
            ],
            [
                'name' => 'transport_officer',
                'display_name' => 'Transport Officer',
                'description' => 'Responsible for managing school transportation (if applicable)',
            ],
            [
                'name' => 'school_driver',
                'display_name' => 'School Driver',
                'description' => 'Responsible for driving school vehicles (if applicable)',
            ],
            [
                'name' => 'catering_manager',
                'display_name' => 'Catering Manager',
                'description' => 'Responsible for managing the school\'s catering services (if applicable)',
            ],
            [
                'name' => 'cook',
                'display_name' => 'Cook',
                'description' => 'Responsible for preparing meals in the school cafeteria (if applicable)',
            ],
            [
                'name' => 'kitchen_staff',
                'display_name' => 'Kitchen Staff',
                'description' => 'Assists with food preparation and kitchen duties (if applicable)',
            ],
            [
                'name' => 'public_relations_officer',
                'display_name' => 'Public Relations Officer',
                'description' => 'Responsible for managing the school\'s public image and communication',
            ],
            [
                'name' => 'information_officer',
                'display_name' => 'Information Officer',
                'description' => 'Responsible for disseminating information within and outside the school',
            ],
            [
                'name' => 'patron_club_society',
                'display_name' => 'Patron/Matron of Club/Society',
                'description' => 'Teacher assigned to oversee and guide a specific student club or society',
            ],
            [
                'name' => 'super-admin',
                'display_name' => 'Super Admin',
                'description' => 'Has full system access across all schools and modules',
            ],
            [
                'name' => 'school-owner',
                'display_name' => 'School Owner',
                'description' => 'Owner or proprietor of the school with administrative oversight',
            ],
            [
                'name' => 'it-support',
                'display_name' => 'IT Support',
                'description' => 'Manages and supports the school management software and tech systems',
            ],
            [
                'name' => 'auditor',
                'display_name' => 'Auditor',
                'description' => 'Inspects records and operations for compliance and accuracy',
            ],
        ];


        // Create roles and assign permissions
        foreach ($roles as $roleData) {
            $role = Role::updateOrCreate(['name' => $roleData['name']], [
                'display_name' => $roleData['display_name'],
                'description' => $roleData['description'],
            ]);

            // Assign permissions if permissions are defined for the role
            if (isset($roleData['permissions'])) {
                foreach ($roleData['permissions'] as $permissionName) {
                    $permission = Permission::firstOrCreate(['name' => $permissionName]);
                    $role->givePermissionTo($permission);
                }
            }
        }
    }
}
