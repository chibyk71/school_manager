<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
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
            'feedbackable_id' => 'required|string|exists:users,id', // Adjust based on feedbackable models
            'feedbackable_type' => 'required|string|in:App\\Models\\User', // Add other models as needed
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'status' => 'sometimes|in:pending,reviewed,resolved',
            'handled_by' => 'nullable|exists:users,id',
            'category' => 'required|in:Complaint,Suggestion,Appreciation',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();
        if ($school && !$this->has('school_id')) {
            $this->merge(['school_id' => $school->id]);
        }
    }
}
