<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default roles and their permissions
        $roles = [
            'Principal' => ['manage_users', 'manage_roles', 'manage_permissions'],
            'Teacher' => [
                // Dashboard and UI
                'view-dashboard',
                'view-sidebar',

                // Class and Subject Management
                'view-assigned-classes',
                'assign-homework',
                'view-homework',
                'create-homework',
                'update-homework',
                'delete-homework',
                'upload-study-material',
                'view-study-material',
                'view-class-routines',
                'view-syllabus',
                'view-lesson-plans',

                // Attendance and Behavior
                'view-attendance-records',
                'mark-attendance',
                'update-attendance',
                'view-behavior-records',
                'create-behavior-record',
                'update-behavior-record',

                // Exam and Grading
                'view-exam-schedules',
                'mark-exam-attendance',
                'view-marks-registers',
                'add-marks',
                'update-marks',
                'view-grade-management',
                'generate-marksheet-reports',

                // Communication and Notifications
                'view-notice-boards',
                'participate-in-forum',
                'send-email-sms-notifications',

                // Library
                'access-library-catalog',
                'issue-book',
                'return-book',

                // Virtual Classroom and Integrations
                'oversee-virtual-classes',
                'handle-virtual-classroom-integrations',
            ],
            'Student' => [
                // Dashboard and UI
                'view-dashboard',
                'view-sidebar',

                // Academic Information
                'view-study-material',
                'view-class-routines',
                'view-syllabus',
                'view-assigned-homework',
                'submit-homework',
                'view-lesson-plans',
                'view-topics',

                // Attendance and Behavior
                'view-attendance-records',
                'view-behavior-records',

                // Exams and Grades
                'view-exam-schedules',
                'view-marks-registers',
                'view-grade-management',
                'download-marksheets',
                'view-seat-plans',

                // Library
                'search-library-catalog',
                'borrow-book',
                'return-book',

                // Communication and Notifications
                'view-notice-boards',
                'receive-email-sms-notifications',
                'participate-in-forum',

                // Virtual Classroom and Integrations
                'access-virtual-classes',
                'submit-online-exam',

                // Certificates and IDs
                'view-id-card',
                'view-certificate-records',
                'request-certificate',

                // Miscellaneous
                'view-download-center',
                'access-shared-content-lists',
            ],
            'Librarian' => [
                // Dashboard and UI
                'view-dashboard',
                'view-sidebar',
            
                // Library Management
                'view-library-records',
                'add-book',
                'update-book',
                'delete-book',
                'view-library-members',
                'add-library-member',
                'update-library-member',
                'delete-library-member',
                'issue-book',
                'return-book',
            
                // Communication and Notifications
                'view-notice-boards',
                'send-email-sms-notifications', // Optional, if librarians send notifications about overdue books.
            
                // Reports and Analysis
                'generate-library-reports', // Optional, if your system supports library analytics.
            
                // Inventory and Categories (if managing library-specific inventory)
                'view-inventory-categories',
                'create-inventory-category',
                'update-inventory-category',
                'delete-inventory-category',
            ],
            'Administrator' => [
                // Dashboard and Sidebar
                'view-dashboard',
                'view-sidebar',
                
                // Visitor and Log Management
                'view-visitor-book',
                'add-visitor-entry',
                'update-visitor-entry',
                'delete-visitor-entry',
                'view-phone-call-log',
                'add-phone-call-log-entry',
                'update-phone-call-log-entry',
                'delete-phone-call-log-entry',
                
                // Student and Staff Management
                'view-student-information',
                'add-student',
                'update-student',
                'delete-student',
                'view-staff-directory',
                'add-staff',
                'update-staff',
                'delete-staff',
                
                // Academic Scheduling
                'view-class-routines',
                'create-class-routine',
                'update-class-routine',
                'delete-class-routine',
                'view-exam-schedules',
                'create-exam-schedule',
                'update-exam-schedule',
                'delete-exam-schedule',
                
                // Attendance Management
                'view-attendance-records',
                'mark-attendance',
                'update-attendance',
                'delete-attendance',
                
                // Fees Management
                'view-fees-groups-types',
                'create-fees-invoice',
                'update-fees-invoice',
                'delete-fees-invoice',
                'view-fees-invoices',
                
                // Reports and Logs
                'view-student-promotion-records',
                'generate-student-reports',
                'view-behavior-records',
                'create-behavior-record',
                'update-behavior-record',
                'delete-behavior-record',
                
                // Leave Management
                'view-leave-requests',
                'apply-for-leave',
                
                // Miscellaneous
                'view-notice-boards',
                'send-email-sms-notifications',
                'view-download-center',
                'upload-content',
                'delete-content',
            ],
            'Receptionist' => [
                // Dashboard and Sidebar
                'view-dashboard',
                'view-sidebar',
            
                // Visitor Management
                'view-visitor-book',
                'add-visitor-entry',
                'update-visitor-entry',
                'delete-visitor-entry',
            
                // Phone Call Logs
                'view-phone-call-log',
                'add-phone-call-log-entry',
                'update-phone-call-log-entry',
                'delete-phone-call-log-entry',
            
                // Complaints
                'view-complaints',
                'create-complaint',
                'update-complaint',
            
                // Admission Queries
                'view-admission-queries',
                'create-admission-query',
                'update-admission-query',
            
                // General Communication
                'view-notice-boards',
                'send-email-sms-notifications',
            
                // Leave Requests
                'apply-for-leave',
            ],
            'Parent' => [
                // Dashboard and Sidebar
                'view-dashboard',
                'view-sidebar',
            
                // Student Information
                'view-student-information',
            
                // Attendance
                'view-attendance-records',
            
                // Homework and Study Material
                'view-homework',
                'view-study-material',
            
                // Behavior Records
                'view-behavior-records',
            
                // Exam and Performance
                'view-exam-schedules',
                'view-marks-registers',
                'view-grade-management',
                'generate-marksheet-reports',
            
                // Fees and Payments
                'view-fees-invoices',
                'manage-bank-payments',
            
                // Communication
                'view-notice-boards',
                'send-email-sms-notifications',
            
                // Virtual Classroom and Chat
                'oversee-virtual-classes',
                'view-chat-settings',
            ],
            'Accountant' => [
                // Dashboard and Sidebar
                'view-dashboard',
                'view-sidebar',
            
                // Fees Management
                'view-fees-groups-types',
                'create-fees-group',
                'update-fees-group',
                'delete-fees-group',
                'view-fees-invoices',
                'create-fees-invoice',
                'update-fees-invoice',
                'delete-fees-invoice',
                'manage-bank-payments',
                'view-carry-forward-balances',
            
                // Financial Reports
                'view-profit-loss',
                'view-income-expense',
                'generate-fees-reports',
                'generate-accounts-reports',
            
                // Transactions and Refunds
                'manage-transactions',
                'process-refunds',
            
                // Inventory (Optional, if accountants handle inventory-related transactions)
                'view-inventory-categories',
                'create-inventory-category',
                'update-inventory-category',
                'delete-inventory-category',
                'view-items',
                'add-item',
                'update-item',
                'delete-item',
            
                // Wallet Management
                'view-wallet',
            ]            
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $permissions) {
            $role = Role::updateOrCreate(['name' => $roleName]);

            foreach ($permissions as $permissionName) {
                $permission = Permission::firstOrCreate(['name' => $permissionName]);
                $role->givePermissionTo($permission);
            }
        }
    }
}