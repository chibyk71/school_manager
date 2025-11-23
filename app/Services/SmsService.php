<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter; // The facade from djunehor/laravel-sms
use Throwable;

/**
 * SmsService – Multi-tenant, multi-provider SMS service with fallback
 *
 * Features:
 * - 100% database-driven (no .env credentials per school)
 * - Multiple providers enabled simultaneously
 * - Priority-based fallback (tries fastest/reliable first)
 * - Per-school rate limiting (protects against abuse & cost explosion)
 * - Full logging (success, warning, error)
 * - Best-effort delivery: never throws exception (important for notifications/jobs)
 *
 * Usage:
 *   app(SmsService::class)->send($phone, $message, $school);
 *
 * @package App\Services
 */
class SmsService
{
    /**
     * Provider priority map – lower number = higher priority (tried first)
     * This is defined in the database under sms settings → providers → {name} → priority
     */
    protected const FALLBACK_PRIORITY = 999;

    /**
     * Map provider name (as stored in DB) → actual Concrete class
     */
    protected const PROVIDER_CLASS_MAP = [
        'africas_talking' => Concrete\AfricasTalking::class,
        'beta_sms' => Concrete\BetaSms::class,
        'bulk_sms_nigeria' => Concrete\BulkSmsNigeria::class,
        'gold_sms_247' => Concrete\GoldSms247::class,
        'info_bip' => Concrete\InfoBip::class,
        'kudi_sms' => Concrete\KudiSms::class,
        'mebo_sms' => Concrete\MeboSms::class,
        'multitexter' => Concrete\MultiTexter::class,
        'nigerian_bulk_sms' => Concrete\NigerianBulkSms::class,
        'ring_captcha' => Concrete\RingCaptcha::class,
        'smart_sms' => Concrete\SmartSmsSolutions::class,
        'twilio' => Concrete\Twilio::class,
        'nexmo' => Concrete\Nexmo::class,
        'x_wireless' => Concrete\XWireless::class,
    ];

    /**
     * Send an SMS to a recipient using the school's configured providers
     *
     * @param string $to        Phone number in national or international format (e.g. 08012345678 or 2348012345678)
     * @param string $message   SMS body (max 160 chars recommended)
     * @param School $school    The tenant school instance
     * @return bool             true if at least one provider succeeded
     */
    public function send(string $to, string $message, School $school): bool
    {
        // 1. Load school SMS settings from DB (via ruangdeveloper/laravel-settings)
        $smsSettings = getMergedSettings('sms', $school);

        // If no SMS config or globally disabled
        if (empty($smsSettings['providers'] ?? []) || empty($smsSettings['enabled'] ?? true)) {
            Log::info('SMS globally disabled or no providers configured for school', [
                'school_id' => $school->id,
                'to' => $to,
            ]);
            return false;
        }

        // 2. Apply per-school rate limiting (across all providers)
        if (!$this->checkRateLimit($school, $smsSettings)) {
            Log::warning('SMS rate limit exceeded for school', [
                'school_id' => $school->id,
                'to' => $to,
            ]);
            return false;
        }

        // 3. Build ordered list of enabled providers by priority
        $providers = $this->getOrderedProviders($smsSettings);

        if ($providers->isEmpty()) {
            Log::info('No SMS providers enabled for school', ['school_id' => $school->id]);
            return false;
        }

        // 4. Try each provider in order until one succeeds
        foreach ($providers as $providerName => $config) {
            $success = $this->attemptSendWithProvider(
                to: $to,
                message: $message,
                providerName: $providerName,
                config: $config,
                school: $school,
                globalSender: $smsSettings['global_sender_id'] ?? $school->name
            );

            if ($success) {
                Log::info('SMS sent successfully via provider', [
                    'school_id' => $school->id,
                    'provider' => $providerName,
                    'to' => $to,
                ]);
                return true;
            }
        }

        // All providers failed
        Log::error('All SMS providers failed for school', [
            'school_id' => $school->id,
            'to' => $to,
        ]);

        return false;
    }

    /**
     * Get providers sorted by priority (lowest priority number = tried first)
     */
    private function getOrderedProviders(array $smsSettings): \Illuminate\Support\Collection
    {
        return collect($smsSettings['providers'] ?? [])
            ->filter(fn($config) => !empty($config['enabled']))
            ->sortBy(fn($config) => $config['priority'] ?? self::FALLBACK_PRIORITY);
    }

    /**
     * Apply per-school rate limiting using Laravel's RateLimiter
     */
    private function checkRateLimit(School $school, array $smsSettings): bool
    {
        $limit = $smsSettings['rate_limit_per_minute'] ?? 500;
        $key = "sms:school:{$school->id}";

        $executed = RateLimiter::attempt(
            $key,
            $limit,
            fn() => true,
            60 // 1 minute decay
        );

        return $executed !== false;
    }

    /**
     * Attempt to send via a single provider
     */
    private function attemptSendWithProvider(
        string $to,
        string $message,
        string $providerName,
        array $config,
        School $school,
        string $globalSender
    ): bool {
        $providerClass = self::PROVIDER_CLASS_MAP[strtolower($providerName)] ?? null;

        if (!$providerClass) {
            Log::warning('Unsupported SMS provider configured', [
                'provider' => $providerName,
                'school_id' => $school->id,
            ]);
            return false;
        }
        // Merge provider-specific config with global settings
        $senderId = $config['sender_id'] ?? $globalSender;

        try {
            // djunehor/laravel-sms fluent API
            $sent = send_sms($message, $to, $senderId, $providerName);

            $this->logNotification(
                school: $school,
                notifiable: $notifiable ?? null,
                channel: 'sms',
                recipient: $to,
                message: $message,
                provider: $providerName,
                sender: $senderId,
                success: $sent,
                error: $sent ? null : 'Unknown Error',
                metadata: ['duration_ms' => $duration ?? null]
            );

            return (bool) $sent;
        } catch (Throwable $e) {
            $this->logNotification(
                school: $school,
                recipient: $to,
                provider: $providerName,
                channel: 'sms',
                message: $message,
                sender: $senderId,
                success: false,
                error: $e->getMessage(),
                notifiable: $notifiable ?? null
            );

            return false;
        }
    }

    private function logNotification(
        School $school,
        $notifiable,
        string $channel,
        string $recipient,
        string $message,
        ?string $provider,
        ?string $sender,
        bool $success,
        ?string $error = null,
        array $metadata = []
    ): void {
        \App\Models\NotificationLog::create([
            'school_id' => $school->id,
            'notifiable_type' => $notifiable ? get_class($notifiable) : null,
            'notifiable_id' => $notifiable?->id,
            'notification_type' => get_class($this->currentNotification ?? $this),
            'notification_id' => $this->currentNotification?->id ?? null,
            'channel' => $channel,
            'provider' => $success ? $provider : null,
            'recipient' => $recipient,
            'message' => $message,
            'sender' => $sender,
            'success' => $success,
            'error' => $error,
            'segments' => $channel === 'sms' ? $this->calculateSegments($message) : 1,
            'metadata' => $metadata,
            'delivered_at' => $success ? now() : null,
        ]);
    }

    private function calculateSegments(string $message): int
    {
        $len = mb_strlen($message);
        return $len <= 160 ? 1 : ceil($len / 153);
    }
}
