<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Centralised SMS sending.
 *
 * Usage:
 *   app(SmsService::class)->send($phone, $message, $school);
 */
class SmsService
{
    /** @var array<string,string> */
    protected const PROVIDER_ENDPOINTS = [
        'termii'           => 'https://api.ng.termii.com/api/sms/send',
        'twilio'           => 'https://api.twilio.com/2010-04-01/Accounts/{sid}/Messages.json',
        'bulk_sms_nigeria' => 'https://www.bulksmsnigeria.com/api/v1/sms/create',
    ];

    public function send(string $to, string $message, School $school): void
    {
        $settings = getMergedSettings('sms', $school);

        if (! ($settings['sms_enabled'] ?? false)) {
            Log::info('SMS disabled for school', ['school_id' => $school->id]);
            return;
        }

        $provider = strtolower($settings['sms_provider'] ?? 'termii');
        $apiKey   = $settings['sms_api_key'] ?? '';
        $sender   = $settings['sms_sender_id'] ?? $school->name;

        // Rate-limit check (optional but cheap)
        $this->applyRateLimit($school, $settings);

        $response = match ($provider) {
            'termii' => $this->termii($to, $message, $apiKey, $sender),
            'twilio' => $this->twilio($to, $message, $apiKey, $sender),
            'bulk_sms_nigeria' => $this->bulkSmsNigeria($to, $message, $apiKey, $sender),
            default  => throw new RuntimeException("Unsupported SMS provider: {$provider}"),
        };

        if ($response->failed()) {
            Log::error('SMS failed', [
                'provider' => $provider,
                'to'       => $to,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            throw new RuntimeException('Failed to send SMS.');
        }

        Log::info('SMS sent', ['provider' => $provider, 'to' => $to]);
    }

    protected function termii(string $to, string $message, string $apiKey, string $sender): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders(['Content-Type' => 'application/json'])
            ->post(self::PROVIDER_ENDPOINTS['termii'], [
                'to'       => $to,
                'from'     => $sender,
                'sms'      => $message,
                'type'     => 'plain',
                'channel'  => 'generic',
                'api_key'  => $apiKey,
            ]);
    }

    protected function twilio(string $to, string $message, string $apiKey, string $sender): \Illuminate\Http\Client\Response
    {
        // Twilio expects SID:TOKEN – we store the whole string in sms_api_key
        [$sid, $token] = explode(':', $apiKey, 2);

        return Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post(str_replace('{sid}', $sid, self::PROVIDER_ENDPOINTS['twilio']), [
                'To'   => $to,
                'From' => $sender,
                'Body' => $message,
            ]);
    }

    protected function bulkSmsNigeria(string $to, string $message, string $apiKey, string $sender): \Illuminate\Http\Client\Response
    {
        return Http::post(self::PROVIDER_ENDPOINTS['bulk_sms_nigeria'], [
            'api_token' => $apiKey,
            'from'      => $sender,
            'to'        => $to,
            'body'      => $message,
        ]);
    }

    protected function applyRateLimit(School $school, array $settings): void
    {
        $limit = $settings['sms_rate_limit_per_minute'] ?? 500;
        $key   = "sms_rate_limit:{$school->id}";

        $requests = cache()->get($key, 0);
        if ($requests >= $limit) {
            throw new RuntimeException('SMS rate limit exceeded for this school.');
        }

        cache()->put($key, $requests + 1, now()->addMinute());
    }
}
