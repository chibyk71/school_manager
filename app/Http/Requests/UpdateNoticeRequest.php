<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoticeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string|array>
     */
    public function rules(): array
    {
        $school = GetSchoolModel();
        return [
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'is_public' => 'sometimes|required|boolean',
            'effective_date' => 'sometimes|required|date|after_or_equal:today',
            'type' => 'sometimes|required|in:Announcement,Alert,Reminder',
            'recipient_ids' => 'required_if:is_public,false|array',
            'recipient_ids.*' => 'exists:users,id,school_id,' . $school->id,
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();
        if ($school && !$this->has('school_id')) {
            $this->merge(['school_id' => $this->input('is_public') ? null : $school->id]);
        }
    }
}
