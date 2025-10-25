<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRouteRequest;
use App\Http\Requests\UpdateRouteRequest;
use App\Models\Finance\Fee;
use App\Models\Transport\Route;
use App\Models\Transport\Vehicle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Route resources in a multi-tenant school management system.
 */
class RouteController extends Controller
{
    /**
     * Display a listing of routes with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Route::class);

        try {
            $school = GetSchoolModel();

            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'fee_name',
                    'relation' => 'fee',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Route::with(['fee:id,name', 'vehicles:id,name', 'users:id,name'])
                ->withCount(['vehicles', 'users'])
                ->where('school_id', $school?->id)
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $routes = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($routes);
            }

            return Inertia::render('Transport/Routes/Index', [ // UI path: resources/js/Pages/Transport/Routes/Index.vue
                'routes' => $routes,
                'fees' => Fee::where('school_id', $school?->id)->select('id', 'name')->get(),
                'vehicles' => Vehicle::where('school_id', $school?->id)->select('id', 'name')->get(),
                'users' => User::where('school_id', $school?->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch routes: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch routes'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch routes']);
        }
    }

    /**
     * Store a newly created route in storage.
     *
     * @param StoreRouteRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreRouteRequest $request)
    {
        Gate::authorize('create', Route::class);

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id;

            $route = Route::create($validated);
            if ($request->has('vehicle_ids')) {
                $route->vehicles()->sync(
                    collect($validated['vehicle_ids'])->mapWithKeys(function ($vehicleId) use ($request) {
                        return [$vehicleId => ['user_id' => $request->input("vehicle_users.$vehicleId")]];
                    })->toArray()
                );
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Route created successfully'], 201)
                : redirect()->route('routes.index')->with(['success' => 'Route created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create route: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create route'], 500)
                : redirect()->back()->with(['error' => 'Failed to create route'])->withInput();
        }
    }

    /**
     * Display the specified route.
     *
     * @param Request $request
     * @param Route $route
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Route $route)
    {
        Gate::authorize('view', $route);

        try {
            $route->load(['fee:id,name', 'vehicles:id,name', 'users:id,name']);
            return response()->json(['route' => $route]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch route: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch route'], 500);
        }
    }

    /**
     * Update the specified route in storage.
     *
     * @param UpdateRouteRequest $request
     * @param Route $route
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateRouteRequest $request, Route $route)
    {
        Gate::authorize('update', $route);

        try {
            $validated = $request->validated();
            $route->update($validated);
            if ($request->has('vehicle_ids')) {
                $route->vehicles()->sync(
                    collect($validated['vehicle_ids'])->mapWithKeys(function ($vehicleId) use ($request) {
                        return [$vehicleId => ['user_id' => $request->input("vehicle_users.$vehicleId")]];
                    })->toArray()
                );
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Route updated successfully'])
                : redirect()->route('routes.index')->with(['success' => 'Route updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update route: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update route'], 500)
                : redirect()->back()->with(['error' => 'Failed to update route'])->withInput();
        }
    }

    /**
     * Remove the specified route(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Route::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:routes,id',
            ]);

            $school = GetSchoolModel();
            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');

            $query = Route::whereIn('id', $ids)->where('school_id', $school?->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Route(s) deleted successfully' : 'No routes were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('routes.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete routes: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete routes'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete routes']);
        }
    }

    /**
     * Restore a soft-deleted route.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $route = Route::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $route);

        try {
            $route->restore();
            return response()->json(['message' => 'Route restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore route: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore route'], 500);
        }
    }
}
