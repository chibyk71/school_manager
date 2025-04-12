<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomFieldController extends Controller
{
    public function index()
    {
        $customizableResources = ['Staff', 'Guardian', 'Student', 'Certificate', 'Result'];

        $settings = CustomField::when(request()->has('resource'), function ($query) {
            $query->where('model_type', request('resource'));
        })->get();

        return Inertia::render('Settings/School/CustomField', [
            'settings' => $settings,
            'resources' => $customizableResources
        ]);
    }

    public function json (String $resource)
    {
        $customFields = CustomField::where('model_type', $resource)
            ->get()
            ->groupBy('category')
            ->map(function ($fields, $category) {
            return [
                'category' => $category,
                'count' => $fields->count(),
                'fields' => $fields
            ];
            })
            ->values();

        return response()->json($customFields);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        if (empty($validated['model_type'])) {
            return back()->withErrors(['error' => 'Resource is required'])->withInput();
        }

        try {
            // Ensure 'rules' is an array
            $validated['rules'] = isset($validated['rules']) && is_array($validated['rules']) ? $validated['rules'] : [];

            // Add required/nullable rule
            $validated['rules'][] = $request->boolean('required') ? 'required' : 'nullable';

            // Add exist_in rule if options are provided
            if (!empty($validated['options'])) {
                $preparedExistIn = 'in:' . implode(',', $validated['options']);
                $validated['rules'][] = $preparedExistIn;
            }

            CustomField::create($validated);

            return redirect()->route('custom-field.index')
                ->with('success', 'Custom field created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred: ' . $e->getMessage()])->withInput();
        }
    }

    public function update(Request $request, CustomField $customField)
    {
        $validated = $this->validateRequest($request);

        if (empty($validated['model_type'])) {
            return back()->withErrors(['error' => 'Resource is required'])->withInput();
        }

        try {
            // Ensure 'rules' is an array
            $validated['rules'] = isset($validated['rules']) && is_array($validated['rules']) ? $validated['rules'] : [];

            // Add required/nullable rule
            $validated['rules'][] = $request->boolean('required') ? 'required' : 'nullable';

            // Add exist_in rule if options are provided
            if (!empty($validated['options'])) {
                $preparedExistIn = 'in:' . implode(',', $validated['options']);
                $validated['rules'][] = $preparedExistIn;
            }

            $customField->update($validated);

            return redirect()->route('custom-field.index')
                ->with('success', 'Custom field updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred: ' . $e->getMessage()])->withInput();
        }
    }

    private function validateRequest(Request $request)
    {
        return $request->validate([
            'name' => 'required|string|max:255|unique:custom_fields,name,' . ($request->route('customField')->id ?? 'NULL') . ',id',
            'label' => 'required|string|max:255',
            'placeholder' => 'nullable|string|max:255',
            'rules' => 'nullable',
            'classes' => 'nullable|string|max:255',
            'field_type' => 'required|string|in:text,textarea,select,radio,checkbox',
            'options' => 'nullable|array',
            'options.*' => 'string',
            'default_value' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'hint' => 'nullable|string|max:255',
            'sort' => 'nullable|integer',
            'category' => 'nullable|string|max:255',
            'model_type' => 'required|string'
        ]);
    }

    public function destroy(Request $request, CustomField $customField = null)
    {
        try {
            if ($request->filled('ids') && is_array($request->input('ids'))) {
                // Bulk delete custom fields by IDs
                CustomField::withoutFallback()
                    ->whereIn('id', $request->input('ids'))
                    ->delete();

                return response()->json(['success' => true, 'message' => 'Selected custom fields deleted successfully.']);
            } else {
                return response()->json(['success' => false, 'error' => 'No valid custom field(s) specified for deletion.'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'An error occurred while deleting custom field(s).'], 500);
        }
    }

}
