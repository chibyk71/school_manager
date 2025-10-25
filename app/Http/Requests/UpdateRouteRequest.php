<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRouteRequest extends FormRequest
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
        $routeId = $this->route('route') ? $this->route('route')->id : $this->input('id');
        return [
            'name' => 'sometimes|required|string|max:255|unique:routes,name,' . $routeId,
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:active,inactive',
            'starting_point' => 'sometimes|required|string|max:255',
            'ending_point' => 'sometimes|required|string|max:255',
            'distance' => 'sometimes|required|string|max:50',
            'duration' => 'sometimes|required|string|max:50',
            'fee_id' => 'nullable|exists:fees,id,school_id,' . $school->id,
            'vehicle_ids' => 'nullable|array',
            'vehicle_ids.*' => 'exists:vehicles,id,school_id,' . $school->id,
            'vehicle_users' => 'nullable|array',
            'vehicle_users.*' => 'nullable|exists:users,id,school_id,' . $school->id,
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
