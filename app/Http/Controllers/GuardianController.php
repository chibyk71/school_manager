<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGuardianRequest;
use App\Http\Requests\UpdateGuardianRequest;
use App\Models\Guardian;
use App\Services\UserService;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
class GuardianController extends BaseSchoolController
{
    public function __construct(protected UserService $userService) {}

    /**
     * Display a listing of guardians.
     *
     * @param Request $request
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
            return $this->respondWithError($request, 'Unable to load guardians: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new guardian.
     *
     * @return \Inertia\Response| JsonResponse
     */
    public function create(Request $request)
    {
        try {
            $school = $this->getActiveSchool();
            $customFields = $this->getCustomFieldsForForm($school->id, 'App\Models\Guardian');

            return Inertia::render('Guardians/Create', [
                'customFields' => $customFields,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load guardian creation form: ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to load creation form: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created guardian in storage using UserService.
     *
     * @param StoreGuardianRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreGuardianRequest $request)
    {
        try {
            $school = $this->getActiveSchool();
            $data = $request->validated();
            $data['profile_type'] = 'guardian';
            $data['profilable'] = [
                'school_id' => $school->id,
                // Add guardian-specific fields if any
            ];

            // Use UserService to create user + profile + profilable
            $user = $this->userService->create($data);

            // Get the created guardian
            $guardian = $user->guardian;

            DB::transaction(function () use ($data, $guardian) {
                // Save custom fields
                if (!empty($data['custom_fields'])) {
                    $guardian->saveCustomFieldResponses($data['custom_fields']);
                }

                // Sync children
                if (!empty($data['children'])) {
                    $guardian->children()->sync($data['children']);
                }
            });

            return $this->respondWithSuccess($request, 'Guardian created successfully.', 'guardians.index');
        } catch (\Exception $e) {
            Log::error('Failed to create guardian: ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to create guardian: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified guardian.
     *
     * @param Guardian $guardian
     * @return \Inertia\Response| JsonResponse
     */
    public function show(Request $request, Guardian $guardian)
    {
        try {
            Gate::authorize('view', $guardian);
            $guardian->load(['user', 'children', 'customFields']);

            return Inertia::render('Guardians/Show', [
                'guardian' => $guardian,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch guardian ID ' . $guardian->id . ': ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to load guardian: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified guardian.
     *
     * @param Guardian $guardian
     *
     */
    public function edit(Request $request, Guardian $guardian)
    {
        try {
            Gate::authorize('update', $guardian);
            $school = $this->getActiveSchool();
            $customFields = $this->getCustomFieldsForForm($school->id, 'App\Models\Guardian');

            return Inertia::render('Guardians/Edit', [
                'guardian' => $guardian->load(['user', 'children']),
                'customFields' => $customFields,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load guardian edit form for ID ' . $guardian->id . ': ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to load edit form: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified guardian in storage using UserService.
     *
     * @param UpdateGuardianRequest $request
     * @param Guardian $guardian
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateGuardianRequest $request, Guardian $guardian)
    {
        try {
            Gate::authorize('update', $guardian);

            $data = $request->validated();
            $user = $guardian->user;

            // Use UserService to update user + profile
            $this->userService->update($user, $data);

            DB::transaction(function () use ($data, $guardian) {
                // Save custom fields
                if (!empty($data['custom_fields'])) {
                    $guardian->saveCustomFieldResponses($data['custom_fields']);
                }

                // Sync children
                if (isset($data['children'])) {
                    $guardian->children()->sync($data['children']);
                }
            });

            return $this->respondWithSuccess($request, 'Guardian updated successfully.', 'guardians.index');
        } catch (\Exception $e) {
            Log::error('Failed to update guardian ID ' . $guardian->id . ': ' . $e->getMessage());
            return $this->respondWithError($request, 'Unable to update guardian: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified guardian from storage.
     *
     * @param Guardian $guardian
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Guardian $guardian)
    {
        try {
            Gate::authorize('delete', $guardian);
            // TODO see how this works with my already esosting logic for delete composable
            $guardian->delete();

            return $request->wantsJson()
                ? response()->json(['success' => true, 'message' => 'Guardian deleted successfully.'])
                : $this->respondWithSuccess($request, 'Guardian deleted successfully.', 'guardians.index');
        } catch (\Exception $e) {
            Log::error('Failed to delete guardian ID ' . $guardian->id . ': ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['success' => false, 'error' => 'Unable to delete guardian: ' . $e->getMessage()], 500)
                : $this->respondWithError($request, 'Unable to delete guardian: ' . $e->getMessage(), 500);
        }
    }
}
