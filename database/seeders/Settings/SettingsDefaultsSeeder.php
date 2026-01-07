<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class SettingsDefaultsSeeder extends Seeder
{
    /**
     * Default values for all settings modules.
     *
     * Run this seeder once (or on fresh installs) to populate global defaults.
     * School-specific overrides will take precedence via getMergedSettings().
     */
    public function run(): void
    {
        $defaults = [
            // ===================================================================
            // Website & Branding
            // ===================================================================
            '[website.company]' => [
                'legal_name' => 'Your School Name', // Official registered name of the institution
                'tagline' => 'Excellence in Education', // Short slogan displayed on header/footer
                'tax_id' => null, // Tax/VAT registration number (for invoices)
                'public_email' => 'info@yourschool.com', // Contact email shown publicly
                'public_phone' => '+234 800 000 0000', // Public contact phone
                'website_url' => 'https://yourschool.com', // Main website URL
                'social_facebook' => 'https://facebook.com/yourschool', // Facebook page
                'social_twitter' => 'https://twitter.com/yourschool', // Twitter/X handle
                'social_instagram' => 'https://instagram.com/yourschool', // Instagram profile
                'social_linkedin' => null, // LinkedIn (optional)
                'social_youtube' => 'https://youtube.com/@yourschool', // YouTube channel
                'footer_copyright' => '© 2026 Your School Name. All rights reserved.', // Footer copyright text
                'google_maps_embed' => null, // Google Maps iframe code for contact page/footer
                'show_address_footer' => true, // Show physical address in footer
                'show_phone_footer' => true, // Show phone in footer
                'show_email_footer' => true, // Show email in footer
            ],
            'website.themes' => [
                'primary_color' => 'indigo', // Main brand color (Tailwind preset)
                'primary_custom_hex' => null, // Custom hex if 'custom' selected
                'secondary_color' => 'gray', // Secondary/accent color
                'secondary_custom_hex' => null,

                'default_theme' => 'light', // light | dark | auto (system preference)
                'dashboard_layout' => 'modern', // grid | list | cards | modern
                'sidebar_collapsed' => false, // Default sidebar state on login
                'menu_position' => 'left', // left | right
                'compact_mode' => false, // Reduced padding & smaller fonts
            ],

            'website.localization' => [
                'timezone' => 'Africa/Lagos', // Default timezone for dates & scheduling
                'date_format' => 'd/m/Y', // PHP date format used throughout the app
                'time_format' => 'H:i', // 24-hour format (change to 'h:i A' for 12-hour)
                'currency' => 'NGN', // ISO currency code
                'currency_position' => 'before', // before (₦100) or after (100 ₦)
                'decimal_separator' => '.', // Decimal point
                'thousands_separator' => ',', // Thousands separator
                'language' => 'en', // Default app language
                'language_switcher' => true, // Show language selector in header
                'financial_year' => 2026, // Current financial/academic year start
                'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'], // Permitted uploads
                'max_file_upload_size' => 5120, // Max upload size in KB (5MB)
            ],
            'website.social' => [
                // Google OAuth
                'google_enabled' => false,
                'google_client_id' => null,
                'google_client_secret' => null,

                // Facebook OAuth
                'facebook_enabled' => false,
                'facebook_client_id' => null,
                'facebook_client_secret' => null,

                // Microsoft OAuth (Azure AD)
                'microsoft_enabled' => false,
                'microsoft_client_id' => null,
                'microsoft_client_secret' => null,

                // Apple Sign In
                'apple_enabled' => false,
                'apple_client_id' => null,
                'apple_team_id' => null,
                'apple_key_id' => null,
                'apple_private_key' => null,

                // Twitter / X OAuth
                'twitter_enabled' => false,
                'twitter_client_id' => null,
                'twitter_client_secret' => null,

                // LinkedIn OAuth
                'linkedin_enabled' => false,
                'linkedin_client_id' => null,
                'linkedin_client_secret' => null,
            ],

            'website.prefixes' => [
                'student_id' => 'STD',        // Prefix for student enrollment/ID numbers
                'staff_id' => 'STF',          // Prefix for staff/teacher IDs
                'parent_id' => 'PAR',         // Prefix for parent/guardian IDs
                'invoice' => 'INV',           // Invoice number prefix
                'payment' => 'PAY',           // Payment transaction prefix
                'receipt' => 'REC',           // Receipt number prefix
                'class' => 'CLS',             // Class/section code prefix
                'section' => 'SEC',           // Section/group prefix
                'subject' => 'SUB',           // Subject code prefix
                'exam' => 'EXM',              // Exam series prefix
                'fee_type' => 'FEE',          // Fee type code prefix
                'transport_route' => 'TRT',   // Transport route code prefix
                'library_book' => 'LIB',      // Library book accession prefix
            ],
            // TODO:'website.language' => [],

            // ===================================================================
            // General
            // ===================================================================
            'general.connected_apps' => [
                // Third-party service integrations (Slack, Zoom, etc.)
                // Each service can be enabled/disabled with its own config
                'slack' => [
                    'enabled' => false,
                    'config' => [], // webhook_url, channel, etc.
                ],
                'google_calendar' => [
                    'enabled' => false,
                    'config' => [],
                ],
                'gmail' => [
                    'enabled' => false,
                    'config' => [],
                ],
                'github' => [
                    'enabled' => false,
                    'config' => [],
                ],
                'zoom' => [
                    'enabled' => false,
                    'config' => [],
                ],
                'whatsapp' => [
                    'enabled' => false,
                    'config' => [],
                ],
            ],

            'general.notifications' => [
                // Notification event → role matrix: who receives which notification
                'student_admission' => ['admin' => true, 'parent' => true],
                'admission_enquiry' => ['admin' => true],
                'fee_payment' => ['admin' => true, 'parent' => true],
                'fee_due_reminder' => ['admin' => true, 'parent' => true],
                'fee_overdue' => ['admin' => true, 'parent' => true],
                'attendance_low' => ['admin' => true, 'teacher' => true, 'parent' => true],
                'absent_today' => ['admin' => true, 'teacher' => true, 'parent' => true],
                'exam_result_published' => ['admin' => true, 'teacher' => true, 'parent' => true, 'student' => true],
                'new_assignment' => ['teacher' => true, 'student' => true, 'parent' => true],
                'assignment_due' => ['teacher' => true, 'student' => true, 'parent' => true],
                'event_reminder' => ['admin' => true, 'teacher' => true, 'parent' => true],
                'birthday' => ['admin' => true, 'teacher' => true],
                'system_announcement' => ['admin' => true, 'teacher' => true, 'parent' => true, 'student' => true],
                'leave_approved' => ['admin' => true, 'teacher' => true, 'parent' => true, 'student' => true],
                'leave_requested' => ['admin' => true, 'teacher' => true, 'parent' => true, 'student' => true],
                'leave_rejected' => ['admin' => true, 'teacher' => true, 'parent' => true, 'student' => true],
            ],

            'authentication' => [
                // Login throttling
                'login_throttle_max' => 5,        // Max failed attempts before lockout
                'login_throttle_lock' => 15,      // Lockout duration in minutes

                // Password reset
                'reset_password_token_life' => 60, // Token validity in minutes
                'allow_password_reset' => true,
                'password_reset_max_attempts' => 5,

                // Email/OTP verification
                'enable_email_verification' => true,
                'otp_length' => 6,
                'otp_validity' => 10,             // OTP valid for 10 minutes
                'allow_otp_fallback' => true,     // Fallback to email if SMS fails

                // Registration
                'allow_user_registration' => true,
                'account_approval' => false,      // Manual approval required
                'oAuth_registration' => true,     // Allow social login registration
                'show_terms_on_registration' => true,

                // Password confirmation (for sensitive actions)
                'require_password_confirmation' => true,
                'password_confirmation_ttl' => 1800, // 30 minutes

                // Password change
                'allow_password_change' => true,

                // Password complexity rules
                'password_min_length' => 8,
                'password_require_letters' => true,
                'password_require_mixed_case' => true,
                'password_require_numbers' => true,
                'password_require_symbols' => false,

                // Rate limiting for sensitive actions
                'registration_max_attempts' => 5,
                'registration_lock_minutes' => 30,
                'password_update_max_attempts' => 5,
                'password_update_lock_minutes' => 30,
                'otp_verification_max_attempts' => 5,
            ],
            // 'general.api_keys' => [],
            // 'general.user_management' => [],

            // ===================================================================
            // Financial
            // ===================================================================
            'financial.bank_accounts' => [
                'accounts' => [
                    [
                        'id' => 1,
                        'bank_name' => 'Guaranty Trust Bank', // Primary school bank
                        'account_name' => 'Dreams International School - Tuition Account',
                        'account_number' => '0123456789',
                        'branch' => 'Lagos Mainland',
                        'currency' => 'NGN',
                        'notes' => 'For tuition and academic fees only',
                        'is_default' => true, // Shown first on invoices and parent portal
                    ],
                    [
                        'id' => 2,
                        'bank_name' => 'Zenith Bank',
                        'account_name' => 'Dreams International School - Miscellaneous',
                        'account_number' => '0987654321',
                        'branch' => 'Ikeja Branch',
                        'currency' => 'NGN',
                        'notes' => 'For transport, books, uniforms, and other fees',
                        'is_default' => false,
                    ],
                    // Add more as needed for common Nigerian banks (Access, First Bank, UBA, etc.)
                ],
            ],
            'financial.fees' => [
                'allow_offline_payments' => true, // Allow parents to pay via bank transfer/cash
                'offline_payment_instructions' => "Please transfer fees to any of our bank accounts listed on the invoice. Include the student's ID and invoice number in the transfer description.", // Default instructions shown on invoices
                'lock_student_panel_on_default' => false, // Prevent student login if fees overdue
                'print_receipt_after_payment' => true, // Auto-open print dialog after successful payment
                'receipt_single_page' => false, // Force receipt to fit one page (compact layout)

                // Late Payment Penalty
                'late_payment_penalty' => [
                    'enabled' => false, // Master toggle
                    'type' => 'percentage', // percentage or fixed
                    'amount' => 5.0, // 5% or fixed amount
                    'apply_per' => 'once', // once = one-time charge, day = daily
                    'grace_period_days' => 7, // No penalty within first 7 days after due date
                ],
            ],

            'financial.taxes' => [
                'scales' => [ // Multiple tax scales supported
                    [
                        'id' => 1,
                        'name' => 'VAT',
                        'rate' => 7.5,
                        'type' => 'percentage',
                        'is_default' => true,
                    ],
                    // Add more default taxes if common in your region
                    // e.g., Service Tax, Education Cess, etc.
                ],
            ],

            'financial.gateways' => [
                // Payment gateways configuration
                'paystack' => [
                    'enabled' => false,
                    'mode' => 'test', // test or live
                    'credentials' => [
                        'public_key' => '',
                        'secret_key' => '',
                    ],
                ],
                'flutterwave' => [
                    'enabled' => false,
                    'mode' => 'test',
                    'credentials' => [
                        'public_key' => '',
                        'secret_key' => '',
                        'encryption_key' => '',
                    ],
                ],
                'stripe' => [
                    'enabled' => false,
                    'mode' => 'test',
                    'credentials' => [
                        'publishable_key' => '',
                        'secret_key' => '',
                    ],
                ],
                'paypal' => [
                    'enabled' => false,
                    'mode' => 'sandbox', // sandbox or live
                    'credentials' => [
                        'client_id' => '',
                        'secret' => '',
                    ],
                ],
            ],

            // ===================================================================
            // Academic
            // ===================================================================
            // 'academic.years' => [],
            'academic.attendance_rules' => [
                'minimum_percentage' => 75, // Minimum attendance % required for promotion/exams
                'count_late_as_half_day' => true, // Excessive late arrivals count as half-day absence
                'late_grace_minutes' => 15, // Arrival within 15 minutes of start time = on time
                'absent_after_minutes' => 120, // Late beyond 2 hours = marked absent
                'notify_parent_at_percentage' => 85, // Alert parents when attendance drops below 85%
                'mark_weekends_as_holiday' => true, // Weekends automatically treated as non-school days
                'require_reason_for_absence' => true, // Force teacher/parent to enter reason for absence/late
            ],
            // 'academic.grading_scales' => [],

            // ===================================================================
            // 'App & Customization'
            // ===================================================================
            'system.invoice' => [
                'prefix' => 'INV', // Invoice number prefix
                'next_number' => 1, // Starting invoice number
                'number_digits' => 6, // Padding (e.g., INV-000001)
                'template' => 'modern', // Invoice visual style
                'due_days' => 14, // Default payment due in 14 days
                'show_tax' => true, // Display tax line on invoice
                'tax_rate' => 7.5, // Default tax rate (Nigeria VAT)
                'tax_label' => 'VAT', // Label shown on invoice
                'notes' => 'Thank you for your business.', // Default footer notes
                'terms' => 'Payment is due within 14 days. Late payments may incur penalties.', // Terms & conditions
                'show_logo' => true, // Show school/invoice logo
            ],

            'system.gdpr' => [
                'content_text' => 'We use cookies to enhance your experience, analyze site usage, and assist in our marketing efforts. By clicking "Accept All", you consent to our use of cookies.', // Banner message (supports HTML)
                'position' => 'bottom', // Banner position on screen
                'show_accept_button' => true,
                'accept_button_text' => 'Accept All',
                'show_decline_button' => true,
                'decline_button_text' => 'Decline',
                'show_link' => true,
                'link_text' => 'Privacy Policy',
                'link_url' => 'https://yourschool.com/privacy-policy', // External or internal policy page
            ],
            'system.printer' => [
                'paper_width' => 80, // 80mm (most common thermal receipt printer width)
                'margin_top' => 5,
                'margin_bottom' => 5,
                'margin_left' => 3,
                'margin_right' => 3,
                'dpi' => 203, // Standard thermal printer DPI
                'font_size' => 10, // Readable size in points
                'show_school_logo' => true,
                'show_receipt_header' => true,
                'header_text' => 'Official Receipt',
                'footer_text' => 'Thank you for your payment',
                'show_barcode' => true,
                'barcode_type' => 'CODE128', // Most compatible with thermal printers
            ],

            'user_management' => [
                // Online Admission
                'online_admission' => true,
                'online_admission_fee' => 0.00, // Free by default
                'online_admission_instruction' => 'Complete the form below and submit your application. You will receive a confirmation email shortly.',

                // Sign-in Permissions
                'allow_student_signin' => true,
                'allow_parent_signin' => true,
                'allow_teacher_signin' => true,
                'allow_staff_signin' => true,

                // Enrollment ID
                'enrollment_id_format' => '{prefix}{year}{number}', // e.g., STD2026000001
                'enrollment_id_number_length' => 6,

                // Guardian Rules
                'require_guardian_email' => true,
                'max_guardian_students' => 10,

                // Bulk Operations
                'allow_bulk_user_creation' => true,
            ],

            // ===================================================================
            // System & Communication
            // ===================================================================
            'communication.email' => [
                'driver' => 'smtp', // Default to SMTP (most common & reliable for schools)
                'from_name' => 'Your School Name',
                'from_email' => 'no-reply@yourschool.com',
                'reply_to' => null,

                // SMTP defaults (empty – must be configured)
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => '',
                'smtp_password' => '',

                // API providers – disabled by default
                'mailgun_api_key' => '',
                'sendgrid_api_key' => '',
                'postmark_api_key' => '',
                'ses_key' => '',
                'ses_secret' => '',
                'ses_region' => 'us-east-1',
            ],

            'sms' => [
                'enabled' => true, // Master SMS toggle
                'global_sender_id' => 'YourSchool', // Default sender ID (if supported)
                'rate_limit_per_minute' => 60, // Prevent abuse/spam

                'providers' => [
                    // Example structure – all disabled by default
                    'twilio' => [
                        'enabled' => false,
                        'priority' => 10,
                        'sender_id' => null,
                        'credentials' => [
                            'account_sid' => '',
                            'auth_token' => '',
                        ],
                    ],
                    'nexmo' => [
                        'enabled' => false,
                        'priority' => 20,
                        'sender_id' => null,
                        'credentials' => [
                            'api_key' => '',
                            'api_secret' => '',
                        ],
                    ],
                    // Add others as needed (Africa's Talking, etc.)
                ],
            ],

            'communication.otp' => [
                'delivery_channel' => 'sms', // sms | email | both (SMS primary)
                'fallback_to_email' => true, // Use email if SMS fails
                'sms_template' => 'Your verification code is {code}. Valid for {minutes} minutes.',
                'email_subject' => 'Your Verification Code',
                'email_template' => '<p>Your verification code is <strong>{code}</strong>.<br>Valid for {minutes} minutes.</p>',
                'rate_limit_attempts' => 5, // Max attempts before lockout
                'rate_limit_minutes' => 15, // Lockout duration
            ],

            // ===================================================================
            // Advanced / Other
            // ===================================================================
            'others.maintenance' => [
                'mode' => 'disabled', // Maintenance mode off by default
                'bypass_key' => 'secret-maintenance-key-2026', // Strong default key (change immediately!)
                'custom_url' => null, // Optional branded maintenance page
            ],

            'advanced.storage' => [
                'driver' => 'local', // Safe default – uses server storage
                'enabled' => true,
                'config' => [
                    // S3 credentials – empty by default (must be filled for S3)
                    'key' => '',
                    'secret' => '',
                    'bucket' => '',
                    'region' => 'us-east-1',
                    'url' => null, // Custom domain/endpoint if needed
                ],
            ],
        ];

        foreach ($defaults as $key => $value) {
            SaveOrUpdateSchoolSettings($key, $value);
        }
    }
}
