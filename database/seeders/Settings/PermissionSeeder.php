<?php

namespace Database\Seeders\Settings;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $permissions = [
            [
                'name' => 'view-dashboard',
                'display_name' => 'View Dashboard',
                'description' => 'Access the main dashboard',
            ],
            [
                'name' => 'view-sidebar',
                'display_name' => 'View Sidebar',
                'description' => 'Access the sidebar menu',
            ], //implemented
            [
                'name' => 'view-schools',
                'display_name' => 'View Schools',
                'description' => 'Access the list of schools',
            ], //implemented
            [
                'name' => 'create-school',
                'display_name' => 'Create School',
                'description' => 'Create a new school',
            ], //implemented
            [
                'name' => 'update-school',
                'display_name' => 'Update School',
                'description' => 'Update an existing school',
            ], //implemented
            [
                'name' => 'delete-school',
                'display_name' => 'Delete School',
                'description' => 'Delete a school',
            ],
            [
                'name' => 'customize-sidebar',
                'display_name' => 'Customize Sidebar',
                'description' => 'Customize the sidebar menu',
            ],
            [
                'name' => 'view-administration-settings',
                'display_name' => 'View Administration Settings',
                'description' => 'Access administration settings',
            ],
            [
                'name' => 'edit-administration-settings',
                'display_name' => 'Edit Administration Settings',
                'description' => 'Modify administration settings',
            ],
            [
                'name' => 'view-admission-queries',
                'display_name' => 'View Admission Queries',
                'description' => 'Access admission queries',
            ],
            [
                'name' => 'create-admission-query',
                'display_name' => 'Create Admission Query',
                'description' => 'Create a new admission query',
            ],
            [
                'name' => 'update-admission-query',
                'display_name' => 'Update Admission Query',
                'description' => 'Update an existing admission query',
            ],
            [
                'name' => 'delete-admission-query',
                'display_name' => 'Delete Admission Query',
                'description' => 'Remove an admission query',
            ],
            [
                'name' => 'view-visitor-book',
                'display_name' => 'View Visitor Book',
                'description' => 'Access the visitor book',
            ],
            [
                'name' => 'add-visitor-entry',
                'display_name' => 'Add Visitor Entry',
                'description' => 'Add a new visitor entry',
            ],
            [
                'name' => 'update-visitor-entry',
                'display_name' => 'Update Visitor Entry',
                'description' => 'Update an existing visitor entry',
            ],
            [
                'name' => 'delete-visitor-entry',
                'display_name' => 'Delete Visitor Entry',
                'description' => 'Remove a visitor entry',
            ],
            [
                'name' => 'view-complaints',
                'display_name' => 'View Complaints',
                'description' => 'Access complaints',
            ],
            [
                'name' => 'create-complaint',
                'display_name' => 'Create Complaint',
                'description' => 'Create a new complaint',
            ],
            [
                'name' => 'update-complaint',
                'display_name' => 'Update Complaint',
                'description' => 'Update an existing complaint',
            ],
            [
                'name' => 'delete-complaint',
                'display_name' => 'Delete Complaint',
                'description' => 'Remove a complaint',
            ],
            [
                'name' => 'view-postal-records',
                'display_name' => 'View Postal Records',
                'description' => 'Access postal records',
            ],
            [
                'name' => 'create-postal-record',
                'display_name' => 'Create Postal Record',
                'description' => 'Create a new postal record',
            ],
            [
                'name' => 'update-postal-record',
                'display_name' => 'Update Postal Record',
                'description' => 'Update an existing postal record',
            ],
            [
                'name' => 'delete-postal-record',
                'display_name' => 'Delete Postal Record',
                'description' => 'Remove a postal record',
            ],
            [
                'name' => 'view-phone-call-log',
                'display_name' => 'View Phone Call Log',
                'description' => 'Access phone call log',
            ],
            [
                'name' => 'add-phone-call-log-entry',
                'display_name' => 'Add Phone Call Log Entry',
                'description' => 'Add a new phone call log entry',
            ],
            [
                'name' => 'update-phone-call-log-entry',
                'display_name' => 'Update Phone Call Log Entry',
                'description' => 'Update an existing phone call log entry',
            ],
            [
                'name' => 'delete-phone-call-log-entry',
                'display_name' => 'Delete Phone Call Log Entry',
                'description' => 'Remove a phone call log entry',
            ],
            [
                'name' => 'view-admin-setup',
                'display_name' => 'View Admin Setup',
                'description' => 'Access admin setup',
            ],
            [
                'name' => 'edit-admin-setup',
                'display_name' => 'Edit Admin Setup',
                'description' => 'Modify admin setup',
            ],
            [
                'name' => 'view-id-card-management',
                'display_name' => 'View ID Card and Certificate Management',
                'description' => 'Access ID card and certificate management',
            ],
            [
                'name' => 'create-id-card',
                'display_name' => 'Create ID Card',
                'description' => 'Create a new ID card',
            ],
            [
                'name' => 'create-certificate',
                'display_name' => 'Create Certificate',
                'description' => 'Create a new certificate',
            ],
            [
                'name' => 'update-id-card',
                'display_name' => 'Update ID Card',
                'description' => 'Update an existing ID card',
            ],
            [
                'name' => 'update-certificate',
                'display_name' => 'Update Certificate',
                'description' => 'Update an existing certificate',
            ],
            [
                'name' => 'delete-id-card',
                'display_name' => 'Delete ID Card',
                'description' => 'Remove an ID card',
            ],
            [
                'name' => 'delete-certificate',
                'display_name' => 'Delete Certificate',
                'description' => 'Remove a certificate',
            ],
            [
                'name' => 'view-academics',
                'display_name' => 'View Academics',
                'description' => 'Access academic records',
            ],
            [
                'name' => 'class-sections.view',
                'display_name' => 'View Class Sections',
                'description' => 'View all class sections for a school',
            ],
            [
                'name' => 'class-sections.create',
                'display_name' => 'Create Class Sections',
                'description' => 'Create new class sections for a school',
            ],
            [
                'name' => 'class-sections.update',
                'display_name' => 'Update Class Sections',
                'description' => 'Update existing class sections for a school',
            ],
            [
                'name' => 'class-sections.delete',
                'display_name' => 'Delete Class Sections',
                'description' => 'Delete class sections for a school',
            ],
            [
                'name' => 'class-sections.restore',
                'display_name' => 'Restore Class Sections',
                'description' => 'Restore deleted class sections for a school',
            ],
            [
                'name' => 'class-sections.force-delete',
                'display_name' => 'Force Delete Class Sections',
                'description' => 'Permanently delete class sections for a school',
            ],
            // implemented subjects
            [
                'name' => 'subjects.view',
                'display_name' => 'View Subjects',
                'description' => 'View all subjects for a school',
            ],
            [
                'name' => 'subjects.create',
                'display_name' => 'Create Subjects',
                'description' => 'Create new subjects for a school',
            ],
            [
                'name' => 'subjects.update',
                'display_name' => 'Update Subjects',
                'description' => 'Update existing subjects for a school',
            ],
            [
                'name' => 'subjects.delete',
                'display_name' => 'Delete Subjects',
                'description' => 'Delete subjects for a school',
            ],
            [
                'name' => 'subjects.restore',
                'display_name' => 'Restore Subjects',
                'description' => 'Restore deleted subjects for a school',
            ],
            [
                'name' => 'subjects.force-delete',
                'display_name' => 'Force Delete Subjects',
                'description' => 'Permanently delete subjects for a school',
            ],
            // implemented teacher assignments
            [
                'name' => 'teacher-assignments.view',
                'display_name' => 'View Teacher Assignments',
                'description' => 'View all teacher assignments for a school',
            ],
            [
                'name' => 'teacher-assignments.create',
                'display_name' => 'Create Teacher Assignments',
                'description' => 'Create new teacher assignments for a school',
            ],
            [
                'name' => 'teacher-assignments.update',
                'display_name' => 'Update Teacher Assignments',
                'description' => 'Update existing teacher assignments for a school',
            ],
            [
                'name' => 'teacher-assignments.delete',
                'display_name' => 'Delete Teacher Assignments',
                'description' => 'Delete teacher assignments for a school',
            ],
            [
                'name' => 'teacher-assignments.restore',
                'display_name' => 'Restore Teacher Assignments',
                'description' => 'Restore deleted teacher assignments for a school',
            ],
            [
                'name' => 'teacher-assignments.force-delete',
                'display_name' => 'Force Delete Teacher Assignments',
                'description' => 'Permanently delete teacher assignments for a school',
            ],
            // implemented terms
            [
                'name' => 'terms.view',
                'display_name' => 'View Terms',
                'description' => 'View all terms for a school',
            ],
            [
                'name' => 'terms.create',
                'display_name' => 'Create Terms',
                'description' => 'Create new terms for a school',
            ],
            [
                'name' => 'terms.update',
                'display_name' => 'Update Terms',
                'description' => 'Update existing terms for a school',
            ],
            [
                'name' => 'terms.delete',
                'display_name' => 'Delete Terms',
                'description' => 'Delete terms for a school',
            ],
            [
                'name' => 'terms.restore',
                'display_name' => 'Restore Terms',
                'description' => 'Restore deleted terms for a school',
            ],
            [
                'name' => 'terms.force-delete',
                'display_name' => 'Force Delete Terms',
                'description' => 'Permanently delete terms for a school',
            ],
            // implemented timetables
            [
                'name' => 'timetables.view',
                'display_name' => 'View Timetables',
                'description' => 'View all timetables for a school',
            ],
            [
                'name' => 'timetables.create',
                'display_name' => 'Create Timetables',
                'description' => 'Create new timetables for a school',
            ],
            [
                'name' => 'timetables.update',
                'display_name' => 'Update Timetables',
                'description' => 'Update existing timetables for a school',
            ],
            [
                'name' => 'timetables.delete',
                'display_name' => 'Delete Timetables',
                'description' => 'Delete timetables for a school',
            ],
            [
                'name' => 'timetables.restore',
                'display_name' => 'Restore Timetables',
                'description' => 'Restore deleted timetables for a school',
            ],
            [
                'name' => 'timetables.force-delete',
                'display_name' => 'Force Delete Timetables',
                'description' => 'Permanently delete timetables for a school',
            ],
            // implemented timetable details
            [
                'name' => 'timetable-details.view',
                'display_name' => 'View Timetable Details',
                'description' => 'View all timetable details for a school',
            ],
            [
                'name' => 'timetable-details.create',
                'display_name' => 'Create Timetable Details',
                'description' => 'Create new timetable details for a school',
            ],
            [
                'name' => 'timetable-details.update',
                'display_name' => 'Update Timetable Details',
                'description' => 'Update existing timetable details for a school',
            ],
            [
                'name' => 'timetable-details.delete',
                'display_name' => 'Delete Timetable Details',
                'description' => 'Delete timetable details for a school',
            ],
            [
                'name' => 'timetable-details.restore',
                'display_name' => 'Restore Timetable Details',
                'description' => 'Restore deleted timetable details for a school',
            ],
            [
                'name' => 'timetable-details.force-delete',
                'display_name' => 'Force Delete Timetable Details',
                'description' => 'Permanently delete timetable details for a school',
            ],
            // implemented departments
            [
                'name' => 'departments.view',
                'display_name' => 'View Departments',
                'description' => 'View all departments for a school',
            ],
            [
                'name' => 'departments.create',
                'display_name' => 'Create Departments',
                'description' => 'Create new departments for a school',
            ],
            [
                'name' => 'departments.update',
                'display_name' => 'Update Departments',
                'description' => 'Update existing departments for a school',
            ],
            [
                'name' => 'departments.delete',
                'display_name' => 'Delete Departments',
                'description' => 'Delete departments for a school',
            ],
            [
                'name' => 'departments.restore',
                'display_name' => 'Restore Departments',
                'description' => 'Restore deleted departments for a school',
            ],
            [
                'name' => 'departments.force-delete',
                'display_name' => 'Force Delete Departments',
                'description' => 'Permanently delete departments for a school',
            ],
            [
                'name' => 'departments.assign-role',
                'display_name' => 'Assign Roles to Departments',
                'description' => 'Assign roles to departments',
            ],
            [
                'name' => 'departments.view-users',
                'display_name' => 'View Department Users',
                'description' => 'View users in a department',
            ],
            // implemented leave allocations
            [
                'name' => 'leave-allocations.view',
                'display_name' => 'View Leave Allocations',
                'description' => 'View all leave allocations for a school',
            ],
            [
                'name' => 'leave-allocations.create',
                'display_name' => 'Create Leave Allocations',
                'description' => 'Create new leave allocations for a school',
            ],
            [
                'name' => 'leave-allocations.update',
                'display_name' => 'Update Leave Allocations',
                'description' => 'Update existing leave allocations for a school',
            ],
            [
                'name' => 'leave-allocations.delete',
                'display_name' => 'Delete Leave Allocations',
                'description' => 'Delete leave allocations for a school',
            ],
            [
                'name' => 'leave-allocations.restore',
                'display_name' => 'Restore Leave Allocations',
                'description' => 'Restore deleted leave allocations for a school',
            ],
            [
                'name' => 'leave-allocations.force-delete',
                'display_name' => 'Force Delete Leave Allocations',
                'description' => 'Permanently delete leave allocations for a school',
            ],
            // implemented leave ledgers
            [
                'name' => 'leave-ledgers.view',
                'display_name' => 'View Leave Ledger Entries',
                'description' => 'View all leave ledger entries for a school',
            ],
            [
                'name' => 'leave-ledgers.create',
                'display_name' => 'Create Leave Ledger Entries',
                'description' => 'Create new leave ledger entries for a school',
            ],
            [
                'name' => 'leave-ledgers.update',
                'display_name' => 'Update Leave Ledger Entries',
                'description' => 'Update existing leave ledger entries for a school',
            ],
            [
                'name' => 'leave-ledgers.delete',
                'display_name' => 'Delete Leave Ledger Entries',
                'description' => 'Delete leave ledger entries for a school',
            ],
            [
                'name' => 'leave-ledgers.restore',
                'display_name' => 'Restore Leave Ledger Entries',
                'description' => 'Restore deleted leave ledger entries for a school',
            ],
            [
                'name' => 'leave-ledgers.force-delete',
                'display_name' => 'Force Delete Leave Ledger Entries',
                'description' => 'Permanently delete leave ledger entries for a school',
            ],
            // implemented leave requests
            [
                'name' => 'leave-requests.view',
                'display_name' => 'View Leave Requests',
                'description' => 'View all leave requests for a school',
            ],
            [
                'name' => 'leave-requests.create',
                'display_name' => 'Create Leave Requests',
                'description' => 'Create new leave requests for a school',
            ],
            [
                'name' => 'leave-requests.update',
                'display_name' => 'Update Leave Requests',
                'description' => 'Update existing leave requests for a school',
            ],
            [
                'name' => 'leave-requests.delete',
                'display_name' => 'Delete Leave Requests',
                'description' => 'Delete leave requests for a school',
            ],
            [
                'name' => 'leave-requests.restore',
                'display_name' => 'Restore Leave Requests',
                'description' => 'Restore deleted leave requests for a school',
            ],
            [
                'name' => 'leave-requests.force-delete',
                'display_name' => 'Force Delete Leave Requests',
                'description' => 'Permanently delete leave requests for a school',
            ],
            [
                'name' => 'leave-requests.manage-approval',
                'display_name' => 'Manage Leave Request Approvals',
                'description' => 'Approve or reject leave requests',
            ],
            // implemented leave types
            [
                'name' => 'leave-types.view',
                'display_name' => 'View Leave Types',
                'description' => 'View all leave types for a school',
            ],
            [
                'name' => 'leave-types.create',
                'display_name' => 'Create Leave Types',
                'description' => 'Create new leave types for a school',
            ],
            [
                'name' => 'leave-types.update',
                'display_name' => 'Update Leave Types',
                'description' => 'Update existing leave types for a school',
            ],
            [
                'name' => 'leave-types.delete',
                'display_name' => 'Delete Leave Types',
                'description' => 'Delete leave types for a school',
            ],
            [
                'name' => 'leave-types.restore',
                'display_name' => 'Restore Leave Types',
                'description' => 'Restore deleted leave types for a school',
            ],
            [
                'name' => 'leave-types.force-delete',
                'display_name' => 'Force Delete Leave Types',
                'description' => 'Permanently delete leave types for a school',
            ],
            // Implemented Salaries
            [
                'name' => 'salaries.view',
                'display_name' => 'View Salaries',
                'description' => 'View all salaries for a school',
            ],
            [
                'name' => 'salaries.create',
                'display_name' => 'Create Salaries',
                'description' => 'Create new salaries for a school',
            ],
            [
                'name' => 'salaries.update',
                'display_name' => 'Update Salaries',
                'description' => 'Update existing salaries for a school',
            ],
            [
                'name' => 'salaries.delete',
                'display_name' => 'Delete Salaries',
                'description' => 'Delete salaries for a school',
            ],
            [
                'name' => 'salaries.restore',
                'display_name' => 'Restore Salaries',
                'description' => 'Restore deleted salaries for a school',
            ],
            [
                'name' => 'salaries.force-delete',
                'display_name' => 'Force Delete Salaries',
                'description' => 'Permanently delete salaries for a school',
            ],
            // Implemented Salary Structures
            [
                'name' => 'salary-structures.view',
                'display_name' => 'View Salary Structures',
                'description' => 'View all salary structures for a school',
            ],
            [
                'name' => 'salary-structures.create',
                'display_name' => 'Create Salary Structures',
                'description' => 'Create new salary structures for a school',
            ],
            [
                'name' => 'salary-structures.update',
                'display_name' => 'Update Salary Structures',
                'description' => 'Update existing salary structures for a school',
            ],
            [
                'name' => 'salary-structures.delete',
                'display_name' => 'Delete Salary Structures',
                'description' => 'Delete salary structures for a school',
            ],
            [
                'name' => 'salary-structures.restore',
                'display_name' => 'Restore Salary Structures',
                'description' => 'Restore deleted salary structures for a school',
            ],
            [
                'name' => 'salary-structures.force-delete',
                'display_name' => 'Force Delete Salary Structures',
                'description' => 'Permanently delete salary structures for a school',
            ],
            // Implemented Salary Addons
            [
                'name' => 'salary-addons.view',
                'display_name' => 'View Salary Addons',
                'description' => 'View all salary addons for a school',
            ],
            [
                'name' => 'salary-addons.create',
                'display_name' => 'Create Salary Addons',
                'description' => 'Create new salary addons for a school',
            ],
            [
                'name' => 'salary-addons.update',
                'display_name' => 'Update Salary Addons',
                'description' => 'Update existing salary addons for a school',
            ],
            [
                'name' => 'salary-addons.delete',
                'display_name' => 'Delete Salary Addons',
                'description' => 'Delete salary addons for a school',
            ],
            [
                'name' => 'salary-addons.restore',
                'display_name' => 'Restore Salary Addons',
                'description' => 'Restore deleted salary addons for a school',
            ],
            [
                'name' => 'salary-addons.force-delete',
                'display_name' => 'Force Delete Salary Addons',
                'description' => 'Permanently delete salary addons for a school',
            ],
            // Implemented Department Role
            [
                'name' => 'department-roles.view',
                'display_name' => 'View Department Role',
                'description' => 'View all department Role for a school',
            ],
            [
                'name' => 'department-roles.create',
                'display_name' => 'Create Department Role',
                'description' => 'Create new department Role for a school',
            ],
            [
                'name' => 'department-roles.update',
                'display_name' => 'Update Department Role',
                'description' => 'Update existing department Role for a school',
            ],
            [
                'name' => 'department-roles.delete',
                'display_name' => 'Delete Department Role',
                'description' => 'Delete department Role for a school',
            ],
            [
                'name' => 'department-roles.restore',
                'display_name' => 'Restore Department Role',
                'description' => 'Restore deleted department Role for a school',
            ],
            [
                'name' => 'department-roles.force-delete',
                'display_name' => 'Force Delete Department Role',
                'description' => 'Permanently delete department Role for a school',
            ],
            // Implemented Expense Permissions
            [
                'name' => 'expenses.view',
                'display_name' => 'View Expense',
                'description' => 'View all expenses for a school',
            ],
            [
                'name' => 'expenses.create',
                'display_name' => 'Create Expense',
                'description' => 'Create new expense for a school',
            ],
            [
                'name' => 'expenses.update',
                'display_name' => 'Update Expense',
                'description' => 'Update existing expense for a school',
            ],
            [
                'name' => 'expenses.delete',
                'display_name' => 'Delete Expense',
                'description' => 'Delete expense for a school',
            ],
            [
                'name' => 'expenses.restore',
                'display_name' => 'Restore Expense',
                'description' => 'Restore deleted expense for a school',
            ],
            [
                'name' => 'expenses.force-delete',
                'display_name' => 'Force Delete Expense',
                'description' => 'Permanently delete expense for a school',
            ],
            // Implemented Fee Permissions
            [
                'name' => 'fees.view',
                'display_name' => 'View Fee',
                'description' => 'View all fees for a school',
            ],
            [
                'name' => 'fees.create',
                'display_name' => 'Create Fee',
                'description' => 'Create new fee for a school',
            ],
            [
                'name' => 'fees.update',
                'display_name' => 'Update Fee',
                'description' => 'Update existing fee for a school',
            ],
            [
                'name' => 'fees.delete',
                'display_name' => 'Delete Fee',
                'description' => 'Delete fee for a school',
            ],
            [
                'name' => 'fees.restore',
                'display_name' => 'Restore Fee',
                'description' => 'Restore deleted fee for a school',
            ],
            [
                'name' => 'fees.force-delete',
                'display_name' => 'Force Delete Fee',
                'description' => 'Permanently delete fee for a school',
            ],
            // Implemented FeeType Permissions
            [
                'name' => 'fee-types.view',
                'display_name' => 'View Fee Type',
                'description' => 'View all fee types for a school',
            ],
            [
                'name' => 'fee-types.create',
                'display_name' => 'Create Fee Type',
                'description' => 'Create new fee type for a school',
            ],
            [
                'name' => 'fee-types.update',
                'display_name' => 'Update Fee Type',
                'description' => 'Update existing fee type for a school',
            ],
            [
                'name' => 'fee-types.delete',
                'display_name' => 'Delete Fee Type',
                'description' => 'Delete fee type for a school',
            ],
            [
                'name' => 'fee-types.restore',
                'display_name' => 'Restore Fee Type',
                'description' => 'Restore deleted fee type for a school',
            ],
            [
                'name' => 'fee-types.force-delete',
                'display_name' => 'Force Delete Fee Type',
                'description' => 'Permanently delete fee type for a school',
            ],
            [
                'name' => 'transactions.view',
                'display_name' => 'View Transaction',
                'description' => 'View all transactions for a school',
            ],
            [
                'name' => 'transactions.create',
                'display_name' => 'Create Transaction',
                'description' => 'Create new transaction for a school',
            ],
            [
                'name' => 'transactions.update',
                'display_name' => 'Update Transaction',
                'description' => 'Update existing transaction for a school',
            ],
            [
                'name' => 'transactions.delete',
                'display_name' => 'Delete Transaction',
                'description' => 'Delete transaction for a school',
            ],
            // Implemented Transaction Permissions
            [
                'name' => 'transactions.restore',
                'display_name' => 'Restore Transaction',
                'description' => 'Restore deleted transaction for a school',
            ],
            [
                'name' => 'transactions.force-delete',
                'display_name' => 'Force Delete Transaction',
                'description' => 'Permanently delete transaction for a school',
            ],
            // Implemented FeeConcession Permissions
            [
                'name' => 'fee-concessions.view',
                'display_name' => 'View Fee Concession',
                'description' => 'View all fee concessions for a school',
            ],
            [
                'name' => 'fee-concessions.create',
                'display_name' => 'Create Fee Concession',
                'description' => 'Create new fee concession for a school',
            ],
            [
                'name' => 'fee-concessions.update',
                'display_name' => 'Update Fee Concession',
                'description' => 'Update existing fee concession for a school',
            ],
            [
                'name' => 'fee-concessions.delete',
                'display_name' => 'Delete Fee Concession',
                'description' => 'Delete fee concession for a school',
            ],
            [
                'name' => 'fee-concessions.restore',
                'display_name' => 'Restore Fee Concession',
                'description' => 'Restore deleted fee concession for a school',
            ],
            [
                'name' => 'fee-concessions.force-delete',
                'display_name' => 'Force Delete Fee Concession',
                'description' => 'Permanently delete fee concession for a school',
            ],
            // Implemented FeeInstallmentDetail Permissions
            [
                'name' => 'fee-installment-details.view',
                'display_name' => 'View Fee Installment Detail',
                'description' => 'View all fee installment details for a school',
            ],
            [
                'name' => 'fee-installment-details.create',
                'display_name' => 'Create Fee Installment Detail',
                'description' => 'Create new fee installment detail for a school',
            ],
            [
                'name' => 'fee-installment-details.update',
                'display_name' => 'Update Fee Installment Detail',
                'description' => 'Update existing fee installment detail for a school',
            ],
            [
                'name' => 'fee-installment-details.delete',
                'display_name' => 'Delete Fee Installment Detail',
                'description' => 'Delete fee installment detail for a school',
            ],
            [
                'name' => 'fee-installment-details.restore',
                'display_name' => 'Restore Fee Installment Detail',
                'description' => 'Restore deleted fee installment detail for a school',
            ],
            [
                'name' => 'fee-installment-details.force-delete',
                'display_name' => 'Force Delete Fee Installment Detail',
                'description' => 'Permanently delete fee installment detail for a school',
            ],
            // Implemented FeeInstallment Permissions
            [
                'name' => 'fee-installments.view',
                'display_name' => 'View Fee Installment',
                'description' => 'View all fee installments for a school',
            ],
            [
                'name' => 'fee-installments.create',
                'display_name' => 'Create Fee Installment',
                'description' => 'Create new fee installment for a school',
            ],
            [
                'name' => 'fee-installments.update',
                'display_name' => 'Update Fee Installment',
                'description' => 'Update existing fee installment for a school',
            ],
            [
                'name' => 'fee-installments.delete',
                'display_name' => 'Delete Fee Installment',
                'description' => 'Delete fee installment for a school',
            ],
            [
                'name' => 'fee-installments.restore',
                'display_name' => 'Restore Fee Installment',
                'description' => 'Restore deleted fee installment for a school',
            ],
            [
                'name' => 'fee-installments.force-delete',
                'display_name' => 'Force Delete Fee Installment',
                'description' => 'Permanently delete fee installment for a school',
            ],
            // Implemented Payment Permissions
            [
                'name' => 'payments.view',
                'display_name' => 'View Payment',
                'description' => 'View all payments for a school',
            ],
            [
                'name' => 'payments.create',
                'display_name' => 'Create Payment',
                'description' => 'Create new payment for a school',
            ],
            [
                'name' => 'payments.update',
                'display_name' => 'Update Payment',
                'description' => 'Update existing payment for a school',
            ],
            [
                'name' => 'payments.delete',
                'display_name' => 'Delete Payment',
                'description' => 'Delete payment for a school',
            ],
            [
                'name' => 'payments.restore',
                'display_name' => 'Restore Payment',
                'description' => 'Restore deleted payment for a school',
            ],
            [
                'name' => 'payments.force-delete',
                'display_name' => 'Force Delete Payment',
                'description' => 'Permanently delete payment for a school',
            ],
            [
                'name' => 'configs.view',
                'display_name' => 'View Configurations',
                'description' => 'View all configurations for a school or system',
            ],
            [
                'name' => 'configs.create',
                'display_name' => 'Create Configurations',
                'description' => 'Create new configurations for a school or system',
            ],
            [
                'name' => 'configs.update',
                'display_name' => 'Update Configurations',
                'description' => 'Update existing configurations for a school or system',
            ],
            [
                'name' => 'configs.delete',
                'display_name' => 'Delete Configurations',
                'description' => 'Delete configurations for a school or system',
            ],
            [
                'name' => 'configs.restore',
                'display_name' => 'Restore Configurations',
                'description' => 'Restore deleted configurations for a school or system',
            ],
            [
                'name' => 'configs.force-delete',
                'display_name' => 'Force Delete Configurations',
                'description' => 'Permanently delete configurations for a school or system',
            ],
            // implemented grades
            [
                'name' => 'grades.view',
                'display_name' => 'View Grades',
                'description' => 'View all grades for a school',
            ],
            [
                'name' => 'grades.create',
                'display_name' => 'Create Grades',
                'description' => 'Create new grades for a school',
            ],
            [
                'name' => 'grades.update',
                'display_name' => 'Update Grades',
                'description' => 'Update existing grades for a school',
            ],
            [
                'name' => 'grades.delete',
                'display_name' => 'Delete Grades',
                'description' => 'Remove grades from a school',
            ],
            [
                'name' => 'grades.restore',
                'display_name' => 'Restore Grades',
                'description' => 'Restore previously deleted grades for a school',
            ],
            [
                'name' => 'grades.force-delete',
                'display_name' => 'Force Delete Grades',
                'description' => 'Permanently delete grades',
            ],
            // implemented payrolls
            [
                'name' => 'payrolls.view',
                'display_name' => 'View Payrolls',
                'description' => 'View all payrolls for a school',
            ],
            [
                'name' => 'payrolls.create',
                'display_name' => 'Create Payrolls',
                'description' => 'Create new payrolls for a school',
            ],
            [
                'name' => 'payrolls.mark-as-paid',
                'display_name' => 'Mark Payroll as Paid',
                'description' => 'Mark payrolls as paid',
            ],
            [
                'name' => 'payrolls.update',
                'display_name' => 'Update Payrolls',
                'description' => 'Update existing payrolls for a school',
            ],
            [
                'name' => 'payrolls.delete',
                'display_name' => 'Delete Payrolls',
                'description' => 'Delete payrolls for a school',
            ],
            [
                'name' => 'payrolls.restore',
                'display_name' => 'Restore Payrolls',
                'description' => 'Restore previously deleted payrolls',
            ],
            [
                'name' => 'payrolls.force-delete',
                'display_name' => 'Force Delete Payrolls',
                'description' => 'Permanently delete payrolls',
            ],
            // implemented assignments
            [
                'name' => 'assignments.view',
                'display_name' => 'View Assignments',
                'description' => 'View all assignments for a school',
            ],
            [
                'name' => 'assignments.create',
                'display_name' => 'Create Assignments',
                'description' => 'Create new assignments for a school',
            ],
            [
                'name' => 'assignments.update',
                'display_name' => 'Update Assignments',
                'description' => 'Update existing assignments for a school',
            ],
            [
                'name' => 'assignments.delete',
                'display_name' => 'Delete Assignments',
                'description' => 'Delete assignments for a school',
            ],
            [
                'name' => 'assignments.restore',
                'display_name' => 'Restore Assignments',
                'description' => 'Restore previously deleted assignments',
            ],
            [
                'name' => 'assignments.force-delete',
                'display_name' => 'Force Delete Assignments',
                'description' => 'Permanently delete assignments',
            ],
            // Implemented Assignment submission permissions
            [
                'name' => 'assignment-submissions.view',
                'display_name' => 'View Assignment Submissions',
                'description' => 'View all assignment submissions for a school',
            ],
            [
                'name' => 'assignment-submissions.create',
                'display_name' => 'Create Assignment Submissions',
                'description' => 'Create new assignment submissions for a school',
            ],
            [
                'name' => 'assignment-submissions.update',
                'display_name' => 'Update Assignment Submissions',
                'description' => 'Update existing assignment submissions for a school',
            ],
            [
                'name' => 'assignment-submissions.delete',
                'display_name' => 'Delete Assignment Submissions',
                'description' => 'Delete assignment submissions for a school',
            ],
            [
                'name' => 'assignment-submissions.restore',
                'display_name' => 'Restore Assignment Submissions',
                'description' => 'Restore previously deleted assignment submissions',
            ],
            [
                'name' => 'assignment-submissions.force-delete',
                'display_name' => 'Force Delete Assignment Submissions',
                'description' => 'Permanently delete assignment submissions',
            ],
            // Implemented Book list permissions
            [
                'name' => 'book-lists.view',
                'display_name' => 'View Book Lists',
                'description' => 'View all book list entries for a school',
            ],
            [
                'name' => 'book-lists.create',
                'display_name' => 'Create Book Lists',
                'description' => 'Create new book list entries for a school',
            ],
            [
                'name' => 'book-lists.update',
                'display_name' => 'Update Book Lists',
                'description' => 'Update existing book list entries for a school',
            ],
            [
                'name' => 'book-lists.delete',
                'display_name' => 'Delete Book Lists',
                'description' => 'Delete book list entries for a school',
            ],
            [
                'name' => 'book-lists.restore',
                'display_name' => 'Restore Book Lists',
                'description' => 'Restore previously deleted book list entries',
            ],
            [
                'name' => 'book-lists.force-delete',
                'display_name' => 'Force Delete Book Lists',
                'description' => 'Permanently delete book list entries',
            ],
            // Book order permissions
            [
                'name' => 'book-orders.view',
                'display_name' => 'View Book Orders',
                'description' => 'View all book orders for a school',
            ],
            [
                'name' => 'book-orders.create',
                'display_name' => 'Create Book Orders',
                'description' => 'Create new book orders for a school',
            ],
            [
                'name' => 'book-orders.update',
                'display_name' => 'Update Book Orders',
                'description' => 'Update existing book orders for a school',
            ],
            [
                'name' => 'book-orders.delete',
                'display_name' => 'Delete Book Orders',
                'description' => 'Delete book orders for a school',
            ],
            [
                'name' => 'book-orders.restore',
                'display_name' => 'Restore Book Orders',
                'description' => 'Restore previously deleted book orders',
            ],
            [
                'name' => 'book-orders.force-delete',
                'display_name' => 'Force Delete Book Orders',
                'description' => 'Permanently delete book orders',
            ],
            // Lesson plan permissions
            [
                'name' => 'lesson-plans.view',
                'display_name' => 'View Lesson Plans',
                'description' => 'View all lesson plans for a school',
            ],
            [
                'name' => 'lesson-plans.create',
                'display_name' => 'Create Lesson Plans',
                'description' => 'Create new lesson plans for a school',
            ],
            [
                'name' => 'lesson-plans.update',
                'display_name' => 'Update Lesson Plans',
                'description' => 'Update existing lesson plans for a school',
            ],
            [
                'name' => 'lesson-plans.delete',
                'display_name' => 'Delete Lesson Plans',
                'description' => 'Delete lesson plans for a school',
            ],
            [
                'name' => 'lesson-plans.restore',
                'display_name' => 'Restore Lesson Plans',
                'description' => 'Restore previously deleted lesson plans',
            ],
            [
                'name' => 'lesson-plans.force-delete',
                'display_name' => 'Force Delete Lesson Plans',
                'description' => 'Permanently delete lesson plans',
            ],
            // Lesson plan detail permissions
            [
                'name' => 'lesson-plan-details.view',
                'display_name' => 'View Lesson Plan Details',
                'description' => 'View all lesson plan details for a school',
            ],
            [
                'name' => 'lesson-plan-details.create',
                'display_name' => 'Create Lesson Plan Details',
                'description' => 'Create new lesson plan details for a school',
            ],
            [
                'name' => 'lesson-plan-details.update',
                'display_name' => 'Update Lesson Plan Details',
                'description' => 'Update existing lesson plan details for a school',
            ],
            [
                'name' => 'lesson-plan-details.delete',
                'display_name' => 'Delete Lesson Plan Details',
                'description' => 'Delete lesson plan details for a school',
            ],
            [
                'name' => 'lesson-plan-details.restore',
                'display_name' => 'Restore Lesson Plan Details',
                'description' => 'Restore previously deleted lesson plan details',
            ],
            [
                'name' => 'lesson-plan-details.force-delete',
                'display_name' => 'Force Delete Lesson Plan Details',
                'description' => 'Permanently delete lesson plan details',
            ],
            [
                'name' => 'lesson-plan-details.submit-approval',
                'display_name' => 'Submit Lesson Plan Details for Approval',
                'description' => 'Submit lesson plan details for approval',
            ],
            [
                'name' => 'lesson-plan-details.approve',
                'display_name' => 'Approve Lesson Plan Details',
                'description' => 'Approve lesson plan details',
            ],
            [
                'name' => 'lesson-plan-details.reject',
                'display_name' => 'Reject Lesson Plan Details',
                'description' => 'Reject lesson plan details',
            ],
            // Syllabus permissions
            [
                'name' => 'syllabi.view',
                'display_name' => 'View Syllabi',
                'description' => 'View all syllabi for a school',
            ],
            [
                'name' => 'syllabi.create',
                'display_name' => 'Create Syllabi',
                'description' => 'Create new syllabi for a school',
            ],
            [
                'name' => 'syllabi.update',
                'display_name' => 'Update Syllabi',
                'description' => 'Update existing syllabi for a school',
            ],
            [
                'name' => 'syllabi.delete',
                'display_name' => 'Delete Syllabi',
                'description' => 'Delete syllabi for a school',
            ],
            [
                'name' => 'syllabi.restore',
                'display_name' => 'Restore Syllabi',
                'description' => 'Restore previously deleted syllabi',
            ],
            [
                'name' => 'syllabi.force-delete',
                'display_name' => 'Force Delete Syllabi',
                'description' => 'Permanently delete syllabi',
            ],
            [
                'name' => 'syllabi.submit-approval',
                'display_name' => 'Submit Syllabi for Approval',
                'description' => 'Submit syllabi for approval',
            ],
            [
                'name' => 'syllabi.approve',
                'display_name' => 'Approve Syllabi',
                'description' => 'Approve syllabi',
            ],
            [
                'name' => 'syllabi.reject',
                'display_name' => 'Reject Syllabi',
                'description' => 'Reject syllabi',
            ],
            // Syllabus detail permissions
            [
                'name' => 'syllabus-details.view',
                'display_name' => 'View Syllabus Details',
                'description' => 'View all syllabus details for a school',
            ],
            [
                'name' => 'syllabus-details.create',
                'display_name' => 'Create Syllabus Details',
                'description' => 'Create new syllabus details for a school',
            ],
            [
                'name' => 'syllabus-details.update',
                'display_name' => 'Update Syllabus Details',
                'description' => 'Update existing syllabus details for a school',
            ],
            [
                'name' => 'syllabus-details.delete',
                'display_name' => 'Delete Syllabus Details',
                'description' => 'Delete syllabus details for a school',
            ],
            [
                'name' => 'syllabus-details.restore',
                'display_name' => 'Restore Syllabus Details',
                'description' => 'Restore previously deleted syllabus details',
            ],
            [
                'name' => 'syllabus-details.force-delete',
                'display_name' => 'Force Delete Syllabus Details',
                'description' => 'Permanently delete syllabus details',
            ],
            [
                'name' => 'syllabus-details.submit-approval',
                'display_name' => 'Submit Syllabus Details for Approval',
                'description' => 'Submit syllabus details for approval',
            ],
            [
                'name' => 'syllabus-details.approve',
                'display_name' => 'Approve Syllabus Details',
                'description' => 'Approve syllabus Details',
            ],
            [
                'name' => 'syllabus-details.reject',
                'display_name' => 'Reject Syllabus Details',
                'description' => 'Reject syllabus Details',
            ],
            // Attendance ledger permissions
            [
                'name' => 'attendance-ledgers.view',
                'display_name' => 'View Attendance Ledgers',
                'description' => 'View all attendance ledgers for a school',
            ],
            [
                'name' => 'attendance-ledgers.create',
                'display_name' => 'Create Attendance Ledgers',
                'description' => 'Create new attendance ledgers for a school',
            ],
            [
                'name' => 'attendance-ledgers.update',
                'display_name' => 'Update Attendance Ledgers',
                'description' => 'Update existing attendance ledgers for a school',
            ],
            [
                'name' => 'attendance-ledgers.delete',
                'display_name' => 'Delete Attendance Ledgers',
                'description' => 'Delete attendance ledgers for a school',
            ],
            [
                'name' => 'attendance-ledgers.restore',
                'display_name' => 'Restore Attendance Ledgers',
                'description' => 'Restore previously deleted attendance ledgers',
            ],
            [
                'name' => 'attendance-ledgers.force-delete',
                'display_name' => 'Force Delete Attendance Ledgers',
                'description' => 'Permanently delete attendance ledgers',
            ],
            // Attendance session permissions
            [
                'name' => 'attendance-sessions.view',
                'display_name' => 'View Attendance Sessions',
                'description' => 'View all attendance sessions for a school',
            ],
            [
                'name' => 'attendance-sessions.create',
                'display_name' => 'Create Attendance Sessions',
                'description' => 'Create new attendance sessions for a school',
            ],
            [
                'name' => 'attendance-sessions.update',
                'display_name' => 'Update Attendance Sessions',
                'description' => 'Update existing attendance sessions for a school',
            ],
            [
                'name' => 'attendance-sessions.delete',
                'display_name' => 'Delete Attendance Sessions',
                'description' => 'Delete attendance sessions for a school',
            ],
            [
                'name' => 'attendance-sessions.restore',
                'display_name' => 'Restore Attendance Sessions',
                'description' => 'Restore previously deleted attendance sessions',
            ],
            [
                'name' => 'attendance-sessions.force-delete',
                'display_name' => 'Force Delete Attendance Sessions',
                'description' => 'Permanently delete attendance sessions',
            ],
            // Admission permissions
            [
                'name' => 'admissions.view',
                'display_name' => 'View Admissions',
                'description' => 'View all admissions for a school',
            ],
            [
                'name' => 'admissions.create',
                'display_name' => 'Create Admissions',
                'description' => 'Create new admissions for a school',
            ],
            [
                'name' => 'admissions.update',
                'display_name' => 'Update Admissions',
                'description' => 'Update existing admissions for a school',
            ],
            [
                'name' => 'admissions.delete',
                'display_name' => 'Delete Admissions',
                'description' => 'Delete admissions for a school',
            ],
            [
                'name' => 'admissions.restore',
                'display_name' => 'Restore Admissions',
                'description' => 'Restore previously deleted admissions',
            ],
            [
                'name' => 'admissions.force-delete',
                'display_name' => 'Force Delete Admissions',
                'description' => 'Permanently delete admissions',
            ],
            //  Event permissions
            [
                'name' => 'events.view',
                'display_name' => 'View Events',
                'description' => 'View all events for a school',
            ],
            [
                'name' => 'events.create',
                'display_name' => 'Create Events',
                'description' => 'Create new events for a school',
            ],
            [
                'name' => 'events.update',
                'display_name' => 'Update Events',
                'description' => 'Update existing events for a school',
            ],
            [
                'name' => 'events.delete',
                'display_name' => 'Delete Events',
                'description' => 'Delete events for a school',
            ],
            [
                'name' => 'events.restore',
                'display_name' => 'Restore Events',
                'description' => 'Restore deleted events for a school',
            ],
            [
                'name' => 'events.force-delete',
                'display_name' => 'Force Delete Events',
                'description' => 'Permanently delete events for a school',
            ],
            [
                'name' => 'event-types.view',
                'display_name' => 'View Event Types',
                'description' => 'View all event types',
            ],
            [
                'name' => 'event-types.create',
                'display_name' => 'Create Event Types',
                'description' => 'Create new event types',
            ],
            [
                'name' => 'event-types.update',
                'display_name' => 'Update Event Types',
                'description' => 'Update existing event types',
            ],
            [
                'name' => 'event-types.delete',
                'display_name' => 'Delete Event Types',
                'description' => 'Delete event types',
            ],
            [
                'name' => 'event-types.restore',
                'display_name' => 'Restore Event Types',
                'description' => 'Restore deleted event types',
            ],
            [
                'name' => 'event-types.force-delete',
                'display_name' => 'Force Delete Event Types',
                'description' => 'Permanently delete event types',
            ],
            // Feedback permissions
            [
                'name' => 'feedback.view',
                'display_name' => 'View Feedback',
                'description' => 'View all feedback for a school',
            ],
            [
                'name' => 'feedback.create',
                'display_name' => 'Create Feedback',
                'description' => 'Create new feedback for a school',
            ],
            [
                'name' => 'feedback.update',
                'display_name' => 'Update Feedback',
                'description' => 'Update existing feedback for a school',
            ],
            [
                'name' => 'feedback.delete',
                'display_name' => 'Delete Feedback',
                'description' => 'Delete feedback for a school',
            ],
            [
                'name' => 'feedback.restore',
                'display_name' => 'Restore Feedback',
                'description' => 'Restore deleted feedback for a school',
            ],
            [
                'name' => 'feedback.force-delete',
                'display_name' => 'Force Delete Feedback',
                'description' => 'Permanently delete feedback for a school',
            ],
            [
                'name' => 'notices.view',
                'display_name' => 'View Notices',
                'description' => 'View all notices for a school or public notices',
            ],
            [
                'name' => 'notices.create',
                'display_name' => 'Create Notices',
                'description' => 'Create new notices for a school',
            ],
            [
                'name' => 'notices.update',
                'display_name' => 'Update Notices',
                'description' => 'Update existing notices for a school',
            ],
            [
                'name' => 'notices.delete',
                'display_name' => 'Delete Notices',
                'description' => 'Delete notices for a school',
            ],
            [
                'name' => 'notices.restore',
                'display_name' => 'Restore Notices',
                'description' => 'Restore deleted notices for a school',
            ],
            [
                'name' => 'notices.force-delete',
                'display_name' => 'Force Delete Notices',
                'description' => 'Permanently delete notices for a school',
            ],
            [
                'name' => 'notices.mark-read',
                'display_name' => 'Mark Notices as Read',
                'description' => 'Mark notices as read for a user',
            ],
            [
                'name' => 'routes.view',
                'display_name' => 'View Routes',
                'description' => 'View all routes for a school',
            ],
            [
                'name' => 'routes.create',
                'display_name' => 'Create Routes',
                'description' => 'Create new routes for a school',
            ],
            [
                'name' => 'routes.update',
                'display_name' => 'Update Routes',
                'description' => 'Update existing routes for a school',
            ],
            [
                'name' => 'routes.delete',
                'display_name' => 'Delete Routes',
                'description' => 'Delete routes for a school',
            ],
            [
                'name' => 'routes.restore',
                'display_name' => 'Restore Routes',
                'description' => 'Restore deleted routes for a school',
            ],
            [
                'name' => 'routes.force-delete',
                'display_name' => 'Force Delete Routes',
                'description' => 'Permanently delete routes for a school',
            ],
            // Implemented Vehicle permission
            ['name' => 'vehicles.view', 'display_name' => 'View Vehicles', 'description' => 'View all vehicles for a school'],
            ['name' => 'vehicles.create', 'display_name' => 'Create Vehicles', 'description' => 'Create new vehicles for a school'],
            ['name' => 'vehicles.update', 'display_name' => 'Update Vehicles', 'description' => 'Update existing vehicles for a school'],
            ['name' => 'vehicles.delete', 'display_name' => 'Delete Vehicles', 'description' => 'Delete vehicles for a school'],
            ['name' => 'vehicles.restore', 'display_name' => 'Restore Vehicles', 'description' => 'Restore deleted vehicles for a school'],
            ['name' => 'vehicles.force-delete', 'display_name' => 'Force Delete Vehicles', 'description' => 'Permanently delete vehicles for a school'],
            ['name' => 'vehicles.assign-driver', 'display_name' => 'Assign Driver', 'description' => 'Assign a driver to a vehicle'],
            // Inplemented Vehicle Document Permission
            [
                'name' => 'vehicle-documents.view',
                'display_name' => 'View Vehicle Documents',
                'description' => 'View all vehicle documents for a school',
            ],
            [
                'name' => 'vehicle-documents.create',
                'display_name' => 'Create Vehicle Documents',
                'description' => 'Create new vehicle documents for a school',
            ],
            [
                'name' => 'vehicle-documents.update',
                'display_name' => 'Update Vehicle Documents',
                'description' => 'Update existing vehicle documents for a school',
            ],
            [
                'name' => 'vehicle-documents.delete',
                'display_name' => 'Delete Vehicle Documents',
                'description' => 'Delete vehicle documents for a school',
            ],
            [
                'name' => 'vehicle-documents.restore',
                'display_name' => 'Restore Vehicle Documents',
                'description' => 'Restore deleted vehicle documents for a school',
            ],
            [
                'name' => 'vehicle-documents.force-delete',
                'display_name' => 'Force Delete Vehicle Documents',
                'description' => 'Permanently delete vehicle documents for a school',
            ],
            ['name' => 'vehicle-expenses.view', 'display_name' => 'View Vehicle Expenses', 'description' => 'View all vehicle expenses for a school'],
            ['name' => 'vehicle-expenses.create', 'display_name' => 'Create Vehicle Expenses', 'description' => 'Create new vehicle expenses for a school'],
            ['name' => 'vehicle-expenses.update', 'display_name' => 'Update Vehicle Expenses', 'description' => 'Update existing vehicle expenses for a school'],
            ['name' => 'vehicle-expenses.delete', 'display_name' => 'Delete Vehicle Expenses', 'description' => 'Delete vehicle expenses for a school'],
            ['name' => 'vehicle-expenses.restore', 'display_name' => 'Restore Vehicle Expenses', 'description' => 'Restore deleted vehicle expenses for a school'],
            ['name' => 'vehicle-expenses.force-delete', 'display_name' => 'Force Delete Vehicle Expenses', 'description' => 'Permanently delete vehicle expenses for a school'],
            // Hostel Permissions
            [
                'name' => 'hostel.view-any',
                'display_name' => 'View All Hostels',
                'description' => 'Allows the user to view all hostels for a school.'
            ],
            [
                'name' => 'hostel.view',
                'display_name' => 'View Hostel',
                'description' => 'Allows the user to view a specific hostel for a school.'
            ],
            [
                'name' => 'hostel.create',
                'display_name' => 'Create Hostels',
                'description' => 'Allows the user to create new hostels for a school.'
            ],
            [
                'name' => 'hostel.update',
                'display_name' => 'Update Hostels',
                'description' => 'Allows the user to update existing hostels for a school.'
            ],
            [
                'name' => 'hostel.delete',
                'display_name' => 'Delete Hostels',
                'description' => 'Allows the user to delete hostels for a school.'
            ],
            [
                'name' => 'hostel.restore',
                'display_name' => 'Restore Hostels',
                'description' => 'Allows the user to restore soft-deleted hostels for a school.'
            ],
            [
                'name' => 'hostel.force-delete',
                'display_name' => 'Permanently Delete Hostels',
                'description' => 'Allows the user to permanently delete hostels for a school.'
            ],

            // Hostel Room Permissions
            [
                'name' => 'hostel-room.view-any',
                'display_name' => 'View All Hostel Rooms',
                'description' => 'Allows the user to view all hostel rooms for a school.'
            ],
            [
                'name' => 'hostel-room.view',
                'display_name' => 'View Hostel Room',
                'description' => 'Allows the user to view a specific hostel room for a school.'
            ],
            [
                'name' => 'hostel-room.create',
                'display_name' => 'Create Hostel Rooms',
                'description' => 'Allows the user to create new hostel rooms for a school.'
            ],
            [
                'name' => 'hostel-room.update',
                'display_name' => 'Update Hostel Rooms',
                'description' => 'Allows the user to update existing hostel rooms for a school.'
            ],
            [
                'name' => 'hostel-room.delete',
                'display_name' => 'Delete Hostel Rooms',
                'description' => 'Allows the user to delete hostel rooms for a school.'
            ],
            [
                'name' => 'hostel-room.restore',
                'display_name' => 'Restore Hostel Rooms',
                'description' => 'Allows the user to restore soft-deleted hostel rooms for a school.'
            ],
            [
                'name' => 'hostel-room.force-delete',
                'display_name' => 'Permanently Delete Hostel Rooms',
                'description' => 'Allows the user to permanently delete hostel rooms for a school.'
            ],

            // Hostel Assignment Permissions
            [
                'name' => 'hostel-assignment.view-any',
                'display_name' => 'View All Hostel Assignments',
                'description' => 'Allows the user to view all hostel assignments for a school.'
            ],
            [
                'name' => 'hostel-assignment.view',
                'display_name' => 'View Hostel Assignment',
                'description' => 'Allows the user to view a specific hostel assignment for a school.'
            ],
            [
                'name' => 'hostel-assignment.create',
                'display_name' => 'Create Hostel Assignments',
                'description' => 'Allows the user to create new hostel assignments for a school.'
            ],
            [
                'name' => 'hostel-assignment.update',
                'display_name' => 'Update Hostel Assignments',
                'description' => 'Allows the user to update existing hostel assignments for a school.'
            ],
            [
                'name' => 'hostel-assignment.delete',
                'display_name' => 'Delete Hostel Assignments',
                'description' => 'Allows the user to delete hostel assignments for a school.'
            ],
            [
                'name' => 'hostel-assignment.restore',
                'display_name' => 'Restore Hostel Assignments',
                'description' => 'Allows the user to restore soft-deleted hostel assignments for a school.'
            ],
            [
                'name' => 'hostel-assignment.force-delete',
                'display_name' => 'Permanently Delete Hostel Assignments',
                'description' => 'Allows the user to permanently delete hostel assignments for a school.'
            ],




            [
                'name' => 'generate-teacher-evaluation-reports',
                'display_name' => 'Generate Teacher Evaluation Reports',
                'description' => 'Generate reports for teacher evaluations',
            ],
            [
                'name' => 'view-leave-requests',
                'display_name' => 'View Leave Requests',
                'description' => 'Access leave requests from staff',
            ],
            [
                'name' => 'apply-for-leave',
                'display_name' => 'Apply for Leave',
                'description' => 'Submit a leave application',
            ],
            [
                'name' => 'approve-leave-requests',
                'display_name' => 'Approve Leave Requests',
                'description' => 'Approve leave applications from staff',
            ],
            [
                'name' => 'define-leave-types',
                'display_name' => 'Define Leave Types',
                'description' => 'Set up different types of leave',
            ],
            [
                'name' => 'view-wallet',
                'display_name' => 'View Wallet',
                'description' => 'Access wallet information',
            ],
            [
                'name' => 'manage-transactions',
                'display_name' => 'Manage Transactions',
                'description' => 'Handle financial transactions',
            ],
            [
                'name' => 'process-refunds',
                'display_name' => 'Process Refunds',
                'description' => 'Handle refund transactions',
            ],
            [
                'name' => 'view-profit-loss',
                'display_name' => 'View Profit & Loss',
                'description' => 'Access profit and loss statements',
            ],
            [
                'name' => 'view-inventory-categories',
                'display_name' => 'View Inventory Categories',
                'description' => 'Access inventory categories',
            ],
            [
                'name' => 'create-inventory-category',
                'display_name' => 'Create Inventory Category',
                'description' => 'Create a new inventory category',
            ],
            [
                'name' => 'update-inventory-category',
                'display_name' => 'Update Inventory Category',
                'description' => 'Update an existing inventory category',
            ],
            [
                'name' => 'delete-inventory-category',
                'display_name' => 'Delete Inventory Category',
                'description' => 'Remove an inventory category',
            ],
            [
                'name' => 'view-items',
                'display_name' => 'View Items',
                'description' => 'Access inventory items',
            ],
            [
                'name' => 'add-item',
                'display_name' => 'Add Item',
                'description' => 'Add a new item to inventory',
            ],
            [
                'name' => 'update-item',
                'display_name' => 'Update Item',
                'description' => 'Update an existing inventory item',
            ],
            [
                'name' => 'delete-item',
                'display_name' => 'Delete Item',
                'description' => 'Remove an inventory item',
            ],
            [
                'name' => 'manage-suppliers',
                'display_name' => 'Manage Suppliers',
                'description' => 'Handle supplier information',
            ],
            [
                'name' => 'view-chat-settings',
                'display_name' => 'View Chat Settings',
                'description' => 'Access chat settings',
            ],
            [
                'name' => 'send-chat-invitations',
                'display_name' => 'Send Chat Invitations',
                'description' => 'Invite users to chat',
            ],
            [
                'name' => 'block-users',
                'display_name' => 'Block Users',
                'description' => 'Block users from chatting',
            ],
            [
                'name' => 'view-notice-boards',
                'display_name' => 'View Notice Boards',
                'description' => 'Access notice board announcements',
            ],
            [
                'name' => 'send-email-sms-notifications',
                'display_name' => 'Send Email/SMS Notifications',
                'description' => 'Send notifications via email or SMS',
            ],
            [
                'name' => 'generate-student-reports',
                'display_name' => 'Generate Student Reports',
                'description' => 'Create reports for student performance',
            ],
            [
                'name' => 'generate-exam-reports',
                'display_name' => 'Generate Exam Reports',
                'description' => 'Create reports for exam results',
            ],
            [
                'name' => 'generate-staff-reports',
                'display_name' => 'Generate Staff Reports',
                'description' => 'Create reports for staff performance',
            ],
            [
                'name' => 'generate-fees-reports',
                'display_name' => 'Generate Fees Reports',
                'description' => 'Create reports for fees collection',
            ],
            // Implemented Financial Report
            [
                'name' => 'finance-reports.view',
                'display_name' => 'View Financial Reports',
                'description' => 'View financial reporting dashboard for a school',
            ],
            [
                'name' => 'manage-custom-fields',
                'display_name' => 'Manage Custom Fields',
                'description' => 'Handle custom fields in the application',
            ],
            [
                'name' => 'view-exam-settings',
                'display_name' => 'View Exam Settings',
                'description' => 'Access exam configuration settings',
            ],
            [
                'name' => 'manage-virtual-classroom-integrations',
                'display_name' => 'Manage Virtual Classroom Integrations',
                'description' => 'Handle integrations with virtual classroom tools',
            ],
            [
                'name' => 'manage-virtual-classes-meetings',
                'display_name' => 'Manage Virtual Classes and Meetings',
                'description' => 'Oversee virtual classes and meetings',
            ],
            [
                ' name' => 'manage-ai-content',
                'display_name' => 'Manage AI Content',
                'description' => 'Handle AI-generated content',
            ],
            [
                'name' => 'manage-ai-content-settings',
                'display_name' => 'Manage AI Content Settings',
                'description' => 'Configure settings for AI content generation',
            ],
            [
                'name' => 'manage-whatsapp-support',
                'display_name' => 'Manage WhatsApp Support',
                'description' => 'Oversee WhatsApp support functionalities',
            ],
            [
                'name' => 'manage-whatsapp-agent-management',
                'display_name' => 'Manage WhatsApp Agent Management',
                'description' => 'Handle WhatsApp agent configurations',
            ],
            [
                'name' => 'manage-qr-code-attendance',
                'display_name' => 'Manage QR Code Attendance',
                'description' => 'Oversee QR code attendance tracking',
            ],
            [
                'name' => 'track-attendance',
                'display_name' => 'Track Attendance',
                'description' => 'Monitor attendance records',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}
