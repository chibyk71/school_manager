<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SearchController v2.0
 *
 * Centralized API controller for handling async search requests used by form select components.
 *
 * Key Changes in v2.0:
 * - Added 'total' to response for future virtual scrolling support (e.g., fake array placeholder)
 * - Increased max per_page to 200 for better UX in small-medium schools (common in Nigeria)
 * - Optional school scoping (commented check removed – allow global if no school context, e.g., superadmin)
 * - Added default ordering by name for consistent results
 * - Improved safety defaults and logging
 *
 * This prepares for hybrid loading in AsyncSelect:
 * - Small datasets (<300): load all
 * - Large: use placeholder array + lazy slice loading
 *
 * @package App\Http\Controllers
 */
class SearchController extends Controller
{
    /**
     * Handle the search request for a given resource.
     *
     * @param Request $request The incoming request with query params
     * @param string $resource The resource slug (e.g., 'students', 'staff')
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request, string $resource)
    {
        try {
            // Resolve model class dynamically from resource slug (plural → singular)
            $singular = Str::singular(Str::studly($resource));
            $modelClass = modelClassFromName($singular);

            if (!$modelClass) {
                return response()->json(['error' => 'Invalid resource'], 404);
            }

            // Fetch current school for multi-tenant scoping (may be null for superadmin/global resources)
            $school = GetSchoolModel();

            /** @var Model $model */
            $model = new $modelClass();

            // Build base query
            $query = $model->query();

            // Apply school scoping if method exists and school is present
            if (method_exists($model, 'scopeSchool') && $school?->id) {
                $query->school($school->id);
            }

            // Default ordering for consistent UX (alphabetical by label field)
            $label = $request->input('label_field', 'name');
            $query->orderBy($label, 'asc');

            // Determine value field (usually id)
            $value = $request->input('value_field', 'id');

            // Search fields resolution
            $searchFieldsInput = $request->input('search_fields');
            $searchFields = $searchFieldsInput ? explode(',', $searchFieldsInput) : null;

            if (!$searchFields && method_exists($model, 'getGlobalFilterColumns')) {
                $searchFields = $model->getGlobalFilterColumns();
            }

            $searchFields = $searchFields ?: [$label]; // Fallback to label field

            // Apply global search if provided
            $searchTerm = trim($request->input('search', ''));
            if ($searchTerm) {
                $query->where(function ($q) use ($searchFields, $searchTerm) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
                    }
                });
            }

            // Pagination with increased limit for school data sizes
            $perPage = (int) $request->input('per_page', 50);
            $perPage = min(max($perPage, 10), 200); // Min 10, max 200 for performance

            /** @var LengthAwarePaginator $results */
            $results = $query->paginate($perPage);

            // Transform results to {value, label} format
            $formatted = $results->transform(function ($item) use ($label, $value) {
                return [
                    'value' => $item->{$value},
                    'label' => $item->{$label} ?? 'Unnamed', // Safety fallback
                ];
            });

            // Return full Laravel paginator structure + explicit total
            return response()->json([
                'data'         => $formatted,
                'total'        => $results->total(),        // ← Critical for virtual scroller planning
                'current_page' => $results->currentPage(),
                'last_page'    => $results->lastPage(),
                'per_page'     => $results->perPage(),
                'has_more'     => $results->hasMorePages(),
            ]);

        } catch (\Exception $e) {
            Log::error("Search failed for resource '{$resource}': " . $e->getMessage(), [
                'request'  => $request->except(['_token']), // Exclude sensitive data
                'trace'    => $e->getTraceAsString(),
                'user_id'  => auth()->id() ?? 'guest',
            ]);

            return response()->json(['error' => 'An error occurred while searching. Please try again.'], 500);
        }
    }
}
