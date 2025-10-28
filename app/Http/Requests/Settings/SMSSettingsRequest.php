<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the **SMS** settings group.
 *
 * @group Settings
 */
class SMSSettingsRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        // `permitted()` is called in the controller
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            // Provider
            'sms_provider'   => [
                'required',
                'string',
                'in:termii,twilio,bulk_sms_nigeria',
            ],

            // Credentials
            'sms_api_key'    => ['required', 'string', 'max:255'],
            'sms_sender_id'  => ['nullable', 'string', 'max:50'],

            // Feature toggle
            'sms_enabled'    => ['required', 'boolean'],

            // Optional – rate-limit per school (nice to have)
            'sms_rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    /** @return array<string,string> */
    public function attributes(): array
    {
        return [
            'sms_provider'   => 'SMS provider',
            'sms_api_key'    => 'API key',
            'sms_sender_id'  => 'sender ID',
            'sms_enabled'    => 'SMS enabled',
            'sms_rate_limit_per_minute' => 'SMS per-minute limit',
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'sms_provider.in' => 'Supported providers are Termii, Twilio or BulkSMS Nigeria.',
            'sms_api_key.max' => 'The API key cannot be longer than 255 characters.',
            'sms_sender_id.max'=> 'The sender ID cannot exceed 50 characters.',
        ];
    }
}
