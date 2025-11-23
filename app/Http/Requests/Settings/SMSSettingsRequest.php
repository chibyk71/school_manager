<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SMSSettingsRequest
 *
 * Validates the SMS settings form for multi-tenant schools.
 * Features "smart conditional validation":
 *   → Only validates credentials if the provider is enabled
 *   → Different required fields per provider (e.g. token vs username/password)
 *
 * @package App\Http\Requests\Settings
 */
class SMSSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Permission checked in controller via permitted('manage-settings')
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Global settings
            'enabled'                   => 'sometimes|boolean',
            'global_sender_id'          => 'nullable|string|max:11',
            'rate_limit_per_minute'     => 'nullable|integer|min:10|max:5000',

            // Providers array
            'providers'                 => 'required|array',
            'providers.*.enabled'       => 'required|boolean',
            'providers.*.priority'      => 'required|integer|min:1|max:999',
            'providers.*.sender_id'     => 'nullable|string|max:11',
            'providers.*.credentials'   => 'nullable|array',
        ];
    }

    /**
     * Custom validation logic - runs after basic rules
     * This is where the magic happens: conditional credential validation
     */
    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $providers = $this->input('providers', []);

            foreach ($providers as $name => $config) {
                // Only validate credentials if the provider is enabled
                if (empty($config['enabled'])) {
                    continue;
                }

                $credentials = $config['credentials'] ?? [];

                // Get required fields for this specific provider
                $requiredFields = $this->getRequiredCredentialFields(strtolower($name));

                foreach ($requiredFields as $field) {
                    if (empty($credentials[$field] ?? null)) {
                        $providerDisplay = str_replace('_', ' ', ucfirst($name));
                        $fieldDisplay    = ucwords(str_replace('_', ' ', $field));

                        $validator->errors()->add(
                            "providers.{$name}.credentials.{$field}",
                            "{$fieldDisplay} is required when {$providerDisplay} is enabled."
                        );
                    }
                }
            }
        });
    }

    /**
     * Map provider name → required credential fields
     *
     * Add or modify as needed when new providers are supported
     */
    private function getRequiredCredentialFields(string $provider): array
    {
        return match ($provider) {
            'multitexter',
            'beta_sms',
            'gold_sms_247',
            'kudi_sms',
            'nigerian_bulk_sms' => ['username', 'password'],

            'bulk_sms_nigeria',
            'smart_sms',
            'smslive247' => ['token'],

            'twilio' => ['account_sid', 'auth_token'],

            'africas_talking' => ['api_key', 'username'],

            'nexmo' => ['api_key', 'api_secret'],

            'mebo_sms',
            'ring_captcha' => ['api_key'],

            'x_wireless' => ['api_key', 'client_id'],

            'info_bip' => ['api_key'], // adjust as needed

            default => [],
        };
    }

    /**
     * Custom attribute names for better error messages
     */
    public function attributes(): array
    {
        return [
            'global_sender_id'      => 'Default Sender ID',
            'rate_limit_per_minute' => 'Rate Limit (per minute)',
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'providers.required' => 'At least one SMS provider must be configured.',
            'providers.*.priority.required' => 'Priority is required for all providers.',
            'providers.*.priority.integer'  => 'Priority must be a number.',
        ];
    }
}
