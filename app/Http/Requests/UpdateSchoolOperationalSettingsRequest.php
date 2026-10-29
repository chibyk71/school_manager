<?php

namespace App\Http\Requests;

use App\Models\School;
use App\Settings\Definitions\SchoolOperationalSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates school-scoped operational preferences (Phase 2).
 *
 * School identity is taken from the route-bound School model only — never from
 * a client-supplied school_id field — so settings cannot be written for another
 * school by parameter tampering.
 */
class UpdateSchoolOperationalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var School $school */
        $school = $this->route('school');

        return $school instanceof School
            && Gate::allows('update', $school);
    }

    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        return SchoolOperationalSettings::validationRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'timezone.timezone' => 'The timezone must be a valid IANA timezone identifier.',
            'date_format.in' => 'The date format is not supported.',
            'time_format.in' => 'The time format is not supported.',
            'week_start.in' => 'Week start must be monday, sunday, or saturday.',
            'locale.in' => 'The locale is not supported by this application.',
            'default_currency.in' => 'The currency code is not supported.',
        ];
    }
}
