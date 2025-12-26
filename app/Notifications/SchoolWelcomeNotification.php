<?php

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * SchoolWelcomeNotification
 *
 * Purpose & Context:
 * ------------------
 * This notification is sent to the school administrator (an existing authenticated user)
 * immediately after a new school (tenant) has been successfully created and assigned to them.
 *
 * It serves as the official "Welcome & Confirmation Email" for the new school, providing:
 * - Confirmation that the school has been created and linked to their account
 * - Direct access instructions (no password needed — they log in with existing credentials)
 * - Quick-start guidance tailored for Nigerian school administrators
 * - Links to the new school dashboard and key setup steps
 *
 * Key Design Decisions:
 * ---------------------
 * - Implements ShouldQueue: Sent asynchronously for fast onboarding response
 * - No password handling: Assumes the recipient is an existing user with valid login credentials
 * - Markdown-ready: Uses clean MailMessage lines (easily convertible to full Markdown template)
 * - Nigerian-context aware: References common setup tasks (sessions, terms, fees, SMS)
 * - Action-focused: Clear CTA to log in and start setup
 *
 * Dispatch:
 * ---------
 * Dispatched after school creation when an existing user is assigned as admin:
 *   $admin->notify(new SchoolWelcomeNotification($school));
 *
 * Customization:
 * --------------
 * - Easily extendable to include school-specific data (e.g., code, type)
 * - Can be converted to full Markdown template for richer branding
 * - Supports attachments (e.g., setup guide PDF) if needed later
 */
class SchoolWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The newly created and assigned school instance.
     *
     * @var School
     */
    public $school;

    /**
     * Create a new notification instance.
     *
     * @param  School  $school
     * @return void
     */
    public function __construct(School $school)
    {
        $this->school = $school;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = route('login');
        $dashboardUrl = route('dashboard'); // Will automatically use active school context

        return (new MailMessage)
            ->subject("Your new school '{$this->school->name}' is ready on SchoolManager!")
            ->greeting("Hello {$notifiable->name},")
            ->line("Great news! A new school **{$this->school->name}** has been successfully created and added to your account.")
            ->line('You are now the administrator of this school and can begin setting it up immediately.')
            ->line('You can access it anytime by logging in with your existing account credentials.')
            ->action('Go to Your Dashboard', $dashboardUrl)
            ->line('')
            ->line('**Next Steps to Get Started:**')
            ->line('1. Switch to your new school from the school selector (top-left)')
            ->line('2. Update school profile, upload logo, and configure branding')
            ->line('3. Add school sections and class levels')
            ->line('4. Create the current academic session and terms')
            ->line('5. Invite teachers, register students, and set up fees')
            ->line('6. Enable SMS, parent portal, and online admissions as needed')
            ->line('')
            ->line('We\'re here to help every step of the way.')
            ->line('Email: support@schoolmanager.ng')
            ->line('Phone: +234 800 000 0000')
            ->salutation('Welcome to your new school! The SchoolManager Team');
    }

    /**
     * Get the array representation of the notification (for database channel if used).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'school_id' => $this->school->id,
            'school_name' => $this->school->name,
            'message' => "New school '{$this->school->name}' assigned to your account",
        ];
    }
}
