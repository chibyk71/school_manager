<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use Spatie\MailTemplates\Models\MailTemplate;

class TemplateTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default mail templates
        $defaultTemplates = [
            [
                'mailable' => 'WelcomeEmail',
                'subject' => 'Welcome to Our School',
                'body' => 'Dear {name}, welcome to our school. We are glad to have you with us.'
            ],
            [
                'mailable' => 'PasswordReset',
                'subject' => 'Reset Your Password',
                'body' => 'Click the link below to reset your password: {reset_link}'
            ],
            // Add more default templates as needed
        ];

        // Save the default templates to the database
        foreach ($defaultTemplates as $template) {
            MailTemplate::updateOrCreate(
                ['mailable' => $template['mailable'], 'school_id' => null],
                ['subject' => $template['subject'], 'text_template' => $template['body'], 'html_template'=> $template['body']]
            );
        }
    }
}
