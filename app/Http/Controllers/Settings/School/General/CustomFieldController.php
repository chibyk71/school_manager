<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\School;
use App\Services\SchoolService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing custom fields in a school management system.
 *
 * Handles CRUD operations for custom fields, scoped to schools and branches.
 *
 * @package App\Http\Controllers\Settings\School\General
 */
class CustomFieldController extends Controller
{
    /**
     * Display the custom fields settings page.
     *
     * @param Request $request
     * @return \Inertia\Response
     * @throws \Exception
     */
    public function index(Request $request)
    {
        try {
            $customizableResources = ['Staff', 'Guardian', 'Student', 'Certificate', 'Result'];
            $school = $this->getActiveSchool();

            $query = CustomField::where('school_id', $school->id)
                ->when($request->has('resource'), fn($q) => $q->where('model_type', $request->input('resource')))
                ->withTableQuery($request); // Use HasTableQuery trait for dynamic querying

            $settings = $query->get();

            return Inertia::render('Settings/School/CustomField', [
                'settings' => $settings,
                'resources' => $customizableResources,
                'columns' => ColumnDefinitionHelper::fromModel(new CustomField()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch custom fields: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Unable to load custom fields: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve custom fields for a resource in JSON format.
     *
     * @param string $resource
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function json(string $resource)
    {
        try {
            $school = $this->getActiveSchool();

            $customFields = CustomField::where('school_id', $school->id)
                ->where('model_type', $resource)
                ->get()
                ->groupBy('category')
                ->map(fn($fields, $category) => [
                    'category' => $category ?? 'Uncategorized',
                    'count' => $fields->count(),
                    'fields' => $fields,
                ])
                ->values();

            return response()->json($customFields);
        } catch (\Exception $e) {
            Log::error('Failed to fetch custom fields for JSON: ' . $e->getMessage());
            return response()->json(['error' => 'Unable to fetch custom fields: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a new custom field.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function store(Request $request)
    {
        try {
            $this->authorize('manage-custom-fields');

            $validated = $this->validateRequest($request);

            $school = $this->getActiveSchool();
            $validated['school_id'] = $school->id;

            CustomField::create($validated);

            return redirect()
                ->route('website.custom-field.index')
                ->with('success', 'Custom field created successfully.');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to create custom field: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to create custom field: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing custom field.
     *
     * @param Request $request
     * @param CustomField $customField
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function update(Request $request, CustomField $customField)
    {
        try {
            $this->authorize('manage-custom-fields');

            $validated = $this->validateRequest($request, $customField);

            $school = $this->getActiveSchool();
            $validated['school_id'] = $school->id;

            $customField->update($validated);

            return redirect()
                ->route('website.custom-field')
                ->with('success', 'Custom field updated successfully.');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Failed to update custom field ID ' . $customField->id . ': ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to update custom field: ' . $e->getMessage());
        }
    }

    /**
     * Delete one or more custom fields.
     *
     * @param Request $request
     * @param CustomField|null $customField
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function destroy(Request $request, ?CustomField $customField = null)
    {
        try {
            $this->authorize('manage-custom-fields');

            $school = $this->getActiveSchool();

            if ($request->filled('ids') && is_array($request->input('ids'))) {
                $deleted = CustomField::where('school_id', $school->id)
                    ->whereIn('id', $request->input('ids'))
                    ->delete();

                return response()->json([
                    'success' => true,
                    'message' => $deleted . ' custom field(s) deleted successfully.',
                ]);
            }

            if ($customField && $customField->school_id === $school->id) {
                $customField->delete();
                return response()->json([
                    'success' => true,
                    'message' => 'Custom field deleted successfully.',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'No valid custom field(s) specified for deletion.',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to delete custom field(s): ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Unable to delete custom field(s): ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate the request data for creating or updating a custom field.
     *
     * @param Request $request
     * @param CustomField|null $customField
     * @return array
     */
    private function validateRequest(Request $request, ?CustomField $customField = null): array
    {
        $school = $this->getActiveSchool();
        $rules = [
            'name' => 'required|string|max:255|unique:custom_fields,name,' . ($customField?->id ?? 'NULL') . ',id,school_id,' . $school->id,
            'label' => 'required|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'rules' => 'nullable|array',
            'rules.*' => 'string',
            'classes' => 'nullable|array',
            'classes.*' => 'string|max:255',
            'field_type' => 'required|string|in:text,textarea,select,radio,checkbox',
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'default_value' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'hint' => 'nullable|string|max:255',
            'sort' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:255',
            'model_type' => 'required|string|in:Staff,Guardian,Student,Certificate,Result',
            // 'branch_id' => 'nullable|exists:school_branches,id', i still dont think i need this
        ];

        $validated = $request->validate($rules);

        // Ensure rules is an array and handle required/nullable
        $validated['rules'] = isset($validated['rules']) && is_array($validated['rules']) ? $validated['rules'] : [];
        $validated['rules'][] = $request->boolean('required') ? 'required' : 'nullable';

        // Add in: rule for select/radio/checkbox fields with options
        if (!empty($validated['options']) && in_array($validated['field_type'], ['select', 'radio', 'checkbox'])) {
            $validated['rules'][] = 'in:' . implode(',', $validated['options']);
        }

        return $validated;
    }

    /**
     * Get the active school model.
     *
     * @return \App\Models\School
     * @throws \Exception
     */
    protected function getActiveSchool(): School
    {
        $school = GetSchoolModel();
        if (!$school) {
            throw new \Exception('No active school found.');
        }
        return $school;
    }
}