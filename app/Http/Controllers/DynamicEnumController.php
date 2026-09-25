<?php

/**
 * Dynamic Enum consumer options API (Phase 5).
 *
 * Provides selectable options for frontend form fields by explicit definition key.
 * Resolution uses the authenticated school context (GetSchoolModel) when present,
 * otherwise tenant/default baseline.
 *
 * Admin CRUD lives on Settings\System\DynamicEnumsController (Phase 4).
 */

namespace App\Http\Controllers;

use App\Services\DynamicEnum\DynamicEnumNotConfiguredException;
use App\Services\DynamicEnum\DynamicEnumResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicEnumController extends Controller
{
    public function __construct(
        private readonly DynamicEnumResolver $resolver,
    ) {}

    /**
     * Active/selectable options for a definition key.
     *
     * GET /dynamic-enums/{key}/options
     * Query: include_inactive=1 to include inactive options (historical recognition).
     */
    public function options(Request $request, string $key): JsonResponse
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        $includeInactive = $request->boolean('include_inactive');

        try {
            $resolved = $school !== null
                ? $this->resolver->resolveForSchool($school, $key)
                : $this->resolver->resolve($key);
        } catch (DynamicEnumNotConfiguredException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'options' => [],
            ], 404);
        }

        $options = $includeInactive
            ? $resolved->options
            : $resolved->activeOptions();

        return response()->json([
            'key' => $resolved->key,
            'label' => $resolved->label,
            'options' => $options->map(static fn ($opt) => [
                'value' => $opt->value,
                'label' => $opt->label,
                'is_active' => $opt->isActive,
                'color' => $opt->color,
                'icon' => $opt->icon,
            ])->values()->all(),
        ]);
    }
}
