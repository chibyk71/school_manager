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


    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        if (empty($validated['resource'])) {
            return back()->withErrors(['error' => 'Resource is required'])->withInput();
        }

        try {
            $validated['model_type'] = modelClassFromName($validated['resource'])::class;

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

        if (empty($validated['resource'])) {
            return back()->withErrors(['error' => 'Resource is required'])->withInput();
        }

        try {
            $validated['model_type'] = modelClassFromName($validated['resource'])::class;

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
            'name' => 'required|string|max:255|unique:custom_fields,name',
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
            'resource' => 'required|string'
        ]);
    }

    public function destroy(Request $request, CustomField $customField = null)
    {
        try {
            if ($request->has('ids') && is_array($request->input('ids'))) {
                // Delete multiple custom fields by IDs
                CustomField::whereIn('id', $request->input('ids'))->delete();
                return back()->with('success', 'Selected custom fields deleted successfully.');
            } elseif ($customField) {
                // Delete a single custom field by route model binding
                $customField->delete();
                return back()->with('success', 'Custom field deleted successfully.');
            } else {
                return back()->withErrors(['error' => 'No valid custom field(s) specified for deletion.']);
            }
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred: ' . $e->getMessage()]);
        }
    }
}
