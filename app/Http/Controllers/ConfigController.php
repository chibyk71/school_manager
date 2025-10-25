<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConfigRequest;
use App\Http\Requests\UpdateConfigRequest;
use App\Models\Configuration\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing configurations in a multi-tenant school management system.
 */
class ConfigController extends Controller
{
    /**
     * Display a listing of configurations.
     *
     * Retrieves configurations for the active school or system-wide using dynamic table querying.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Inertia\Response The Inertia response with configurations data.
     */
    public function index(Request $request): \Inertia\Response
    {
        try {
            Gate::authorize('viewAny', Config::class);

            $school = GetSchoolModel();

            // Use HasTableQuery for dynamic querying, searching, sorting, and pagination
            $configs = Config::withTrashed()
                ->all($school?->id)
                ->tableQuery($request);

            return Inertia::render('Settings/Configurations/Index', [ // UI path: resources/js/Pages/Settings/Configurations/Index.vue
                'configs' => $configs,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch configurations: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load configurations.');
        }
    }

    /**
     * Store a newly created configuration.
     *
     * Creates a configuration with validated data, scoped to the active school or system-wide.
     *
     * @param StoreConfigRequest $request The validated request.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     */
    public function store(StoreConfigRequest $request): \Illuminate\Http\RedirectResponse
    {
        try {
            Gate::authorize('create', Config::class);

            $validated = $request->validated();
            $school = GetSchoolModel();

            $config = Config::create(array_merge($validated, [
                'scope_type' => $validated['is_system'] ? null : School::class,
                'scope_id' => $validated['is_system'] ? null : $school?->id,
            ]));

            // Optional: Notify admins
            // \Illuminate\Support\Facades\Notification::send($adminUsers, new ConfigCreated($config));

            return redirect()
                ->route('configs.index')
                ->with('success', 'Configuration created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create configuration: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create configuration: ' . $e->getMessage());
        }
    }

    /**
     * Display a specific configuration.
     *
     * Retrieves a configuration if it belongs to the active school or is system-wide.
     *
     * @param Config $config The configuration to display.
     * @return \Illuminate\Http\JsonResponse The JSON response with configuration data.
     */
    public function show(Config $config): \Illuminate\Http\JsonResponse
    {
        try {
            Gate::authorize('view', $config);

            return response()->json([
                'config' => $config->load('scopeModel', 'configurable'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch configuration: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch configuration.'], 500);
        }
    }

    /**
     * Update an existing configuration.
     *
     * Updates a configuration with validated data, ensuring it belongs to the active school or is system-wide.
     *
     * @param UpdateConfigRequest $request The validated request.
     * @param Config $config The configuration to update.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     */
    public function update(UpdateConfigRequest $request, Config $config): \Illuminate\Http\RedirectResponse
    {
        try {
            Gate::authorize('update', $config);

            $validated = $request->validated();
            $school = GetSchoolModel();

            $config->update(array_merge($validated, [
                'scope_type' => $validated['is_system'] ? null : School::class,
                'scope_id' => $validated['is_system'] ? null : $school?->id,
            ]));

            // Optional: Notify admins
            // \Illuminate\Support\Facades\Notification::send($adminUsers, new ConfigUpdated($config));

            return redirect()
                ->route('configs.index')
                ->with('success', 'Configuration updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update configuration: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update configuration: ' . $e->getMessage());
        }
    }

    /**
     * Delete one or more configurations (soft delete).
     *
     * Supports bulk deletion by IDs for the active school or system-wide.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse The JSON response indicating success or failure.
     */
    public function destroy(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            Gate::authorize('delete', Config::class);

            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:configs,id',
            ]);

            $school = GetSchoolModel();

            Config::whereIn('id', $validated['ids'])
                ->where(function ($query) use ($school) {
                    $query->whereNull('scope_type')
                          ->orWhere(function ($q) use ($school) {
                              $q->where('scope_type', School::class)
                                ->where('scope_id', $school?->id);
                          });
                })
                ->delete();

            // Optional: Notify admins
            // \Illuminate\Support\Facades\Notification::send($adminUsers, new ConfigsDeleted($validated['ids']));

            return response()->json(['message' => 'Configuration(s) deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete configurations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete configurations.', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Restore one or more soft-deleted configurations.
     *
     * Supports bulk restoration by IDs for the active school or system-wide.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse The JSON response indicating success or failure.
     */
    public function restore(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            Gate::authorize('restore', Config::class);

            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:configs,id',
            ]);

            $school = GetSchoolModel();

            Config::withTrashed()
                ->whereIn('id', $validated['ids'])
                ->where(function ($query) use ($school) {
                    $query->whereNull('scope_type')
                          ->orWhere(function ($q) use ($school) {
                              $q->where('scope_type', School::class)
                                ->where('scope_id', $school?->id);
                          });
                })
                ->restore();

            // Optional: Notify admins
            // \Illuminate\Support\Facades\Notification::send($adminUsers, new ConfigsRestored($validated['ids']));

            return response()->json(['message' => 'Configuration(s) restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore configurations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore configurations.', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Permanently delete one or more configurations.
     *
     * Supports bulk force deletion by IDs for the active school or system-wide.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse The JSON response indicating success or failure.
     *
     * TODO remove and merge with delete
     */
    public function forceDestroy(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            Gate::authorize('forceDelete', Config::class);

            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:configs,id',
            ]);

            $school = GetSchoolModel();

            Config::withTrashed()
                ->whereIn('id', $validated['ids'])
                ->where(function ($query) use ($school) {
                    $query->whereNull('scope_type')
                          ->orWhere(function ($q) use ($school) {
                              $q->where('scope_type', School::class)
                                ->where('scope_id', $school?->id);
                          });
                })
                ->forceDelete();

            // Optional: Notify admins
            // \Illuminate\Support\Facades\Notification::send($adminUsers, new ConfigsForceDeleted($validated['ids']));

            return response()->json(['message' => 'Configuration(s) permanently deleted']);
        } catch (\Exception $e) {
            Log::error('Failed to force delete configurations: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to permanently delete configurations.', 'details' => $e->getMessage()], 500);
        }
    }
}
