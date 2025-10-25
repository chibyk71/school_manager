<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoticeRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $school = GetSchoolModel();
        return [
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'is_public' => 'required|boolean',
            'effective_date' => 'required|date|after_or_equal:today',
            'type' => 'required|in:Announcement,Alert,Reminder',
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
