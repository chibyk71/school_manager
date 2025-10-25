<?php

namespace App\Http\Requests;

use App\Models\Housing\HostelAssignment;
use App\Models\Housing\HostelRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request class for storing a new hostel assignment.
 *
 * Validates input data for assigning a student to a hostel room, ensuring it is scoped to the active school,
 * checks room capacity, and prevents duplicate active assignments for the same student.
 */
class StoreHostelAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check(); // Authorization handled in controller via permitted()
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
            'hostel_room_id' => [
                'required',
                'exists:hostel_rooms,id',
                function ($attribute, $value, $fail) use ($school) {
                    $room = HostelRoom::find($value);
                    if (!$room || $room->hostel->school_id !== ($school ? $school->id : 0)) {
                        $fail('The selected hostel room is invalid or not accessible.');
                    }
                    // Check room capacity
                    $currentOccupancy = HostelAssignment::where('hostel_room_id', $value)
                        ->where('status', 'checked-in')
                        ->count();
                    if ($currentOccupancy >= $room->capacity) {
                        $fail('The selected hostel room is at full capacity.');
                    }
                },
            ],
            'student_id' => [
                'required',
                'exists:students,id,school_id,' . ($school ? $school->id : 0),
                Rule::unique('hostel_assignments')->where(function ($query) {
                    return $query->where('status', 'checked-in');
                })->where('student_id', $this->student_id),
            ],
            'status' => 'required|in:checked-in,checked-out',
            'check_in_date' => 'required|date',
            'check_out_date' => 'nullable|date|after_or_equal:check_in_date',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Automatically adds the school_id to the request data if not provided.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $school = GetSchoolModel();
        if ($school && !$this->has('school_id')) {
            $this->merge(['school_id' => $school->id]);
        }
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.unique' => 'This student is already assigned to an active hostel room.',
        ];
    }
}
