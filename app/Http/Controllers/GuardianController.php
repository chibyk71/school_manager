<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuardianRequest;
use App\Http\Requests\UpdateGuardianRequest;
use App\Models\Guardian;
use App\Models\School;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing guardians in the school management system.
 *
 * Handles CRUD operations for guardians, including custom fields and student relationships.
 * Scoped to the active school for multi-tenancy.
 *
 * @package App\Http\Controllers
 */
class GuardianController extends Controller
{
    /**
     * Display a listing of guardians.
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        try {
            $school = $this->getActiveSchool();
            $guardians = Guardian::where('school_id', $school->id)
                ->with(['user', 'children'])
                ->tableQuery($request)
                ->withCustomFields();

            return Inertia::render('Guardians/Index', [
                'guardians' => $guardians,
                'columns' => ColumnDefinitionHelper::fromModel(new Guardian()),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch guardians: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Unable to load guardians: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new guardian.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        try {
            $school = $this->getActiveSchool();
            $customFields = Guardian::getCustomFieldsForForm($school->id, 'App\Models\Guardian');

            return Inertia::render('Guardians/Create', [
                'customFields' => $customFields,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load guardian creation form: ' . $e->getMessage());
            return redirect()->route('guardians.index')->with('error', 'Unable to load creation form: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created guardian in storage.
     *
     * @param StoreGuardianRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreGuardianRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $school = $this->getActiveSchool();
                $data = $request->validated();

                // Create guardian
                $guardian = Guardian::create([
                    'user_id' => $data['user_id'],
                    'school_id' => $school->id,
                ]);

                // Save custom fields
                if (!empty($data['custom_fields'])) {
                    $guardian->saveCustomFieldResponses($data['custom_fields']);
                }

                // Sync children
                if (!empty($data['children'])) {
                    $guardian->children()->sync($data['children']);
                }
            });

            return redirect()->route('guardians.index')->with('success', 'Guardian created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create guardian: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to create guardian: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified guardian.
     *
     * @param Guardian $guardian
     * @return \Inertia\Response
     */
    public function show(Guardian $guardian)
    {
        try {
            $this->authorize('view', $guardian);
            $guardian->load(['user', 'children', 'customFields']);

            return Inertia::render('Guardians/Show', [
                'guardian' => $guardian,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch guardian ID ' . $guardian->id . ': ' . $e->getMessage());
            return redirect()->route('guardians.index')->with('error', 'Unable to load guardian: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified guardian.
     *
     * @param Guardian $guardian
     * @return \Inertia\Response
     */
    public function edit(Guardian $guardian)
    {
        try {
            $this->authorize('update', $guardian);
            $school = $this->getActiveSchool();
            $customFields = Guardian::getCustomFieldsForForm($school->id, 'App\Models\Guardian');

            return Inertia::render('Guardians/Edit', [
                'guardian' => $guardian->load(['user', 'children']),
                'customFields' => $customFields,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load guardian edit form for ID ' . $guardian->id . ': ' . $e->getMessage());
            return redirect()->route('guardians.index')->with('error', 'Unable to load edit form: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified guardian in storage.
     *
     * @param UpdateGuardianRequest $request
     * @param Guardian $guardian
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateGuardianRequest $request, Guardian $guardian)
    {
        try {
            $this->authorize('update', $guardian);

            DB::transaction(function () use ($request, $guardian) {
                $data = $request->validated();

                // Update guardian
                $guardian->update([
                    'user_id' => $data['user_id'] ?? $guardian->user_id,
                ]);

                // Save custom fields
                if (!empty($data['custom_fields'])) {
                    $guardian->saveCustomFieldResponses($data['custom_fields']);
                }

                // Sync children
                if (isset($data['children'])) {
                    $guardian->children()->sync($data['children']);
                }
            });

            return redirect()->route('guardians.index')->with('success', 'Guardian updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update guardian ID ' . $guardian->id . ': ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to update guardian: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified guardian from storage.
     *
     * @param Guardian $guardian
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Guardian $guardian)
    {
        try {
            $this->authorize('delete', $guardian);
            $guardian->delete();

            return response()->json([
                'success' => true,
                'message' => 'Guardian deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete guardian ID ' . $guardian->id . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Unable to delete guardian: ' . $e->getMessage(),
            ], 500);
        }
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

    /**
     * Get custom fields for a form, scoped to the school and model type.
     *
     * @param int $schoolId
     * @param string $modelType
     * @return \Illuminate\Support\Collection
     */
    public static function getCustomFieldsForForm(int $schoolId, string $modelType)
    {
        return \App\Models\CustomField::where('school_id', $schoolId)
            ->where('model_type', $modelType)
            ->orderBy('sort', 'asc')
            ->get()
            ->map(function ($field) {
                return [
                    'id' => $field->id,
                    'name' => $field->name,
                    'label' => $field->label,
                    'field_type' => $field->field_type,
                    'options' => $field->options,
                    'required' => $field->required,
                    'placeholder' => $field->placeholder,
                    'hint' => $field->hint,
                ];
            });
    }
}