<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    public $token;
    public $user;

    public function __construct(string $token, $user)
    {
        $this->token = $token;
        $this->user = $user;
    }

    public function via($notifiable)
    {
        return [request()->delivery_method === 'sms' ? 'sms' : 'mail'];
    }

    public function toMail($notifiable)
    {
        $school = GetSchoolModel();
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
            'school_id' => $school->id,
        ]);

        return (new MailMessage)
            ->subject('Password Reset Request')
            ->line('You are receiving this email because we received a password reset request.')
            ->line('User: ' . $this->user->name)
            ->line('OTP: ' . $this->token)
            ->action('Reset Password', $url)
            ->line('This OTP is valid for ' . (getMergedSettings('authentication', $school)['reset_password_token_life'] ?? 60) . ' minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }

    public function toSms($notifiable)
    {
        $school = GetSchoolModel();
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
            'school_id' => $school->id,
        ]);

        return "Password reset request for {$this->user->name}. OTP: {$this->token}. Use at {$url}. Valid for " . (getMergedSettings('authentication', $school)['reset_password_token_life'] ?? 60) . " minutes.";
    }
}