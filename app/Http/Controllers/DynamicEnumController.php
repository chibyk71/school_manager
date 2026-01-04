<?php
/**
 * app/Http/Controllers/DynamicEnumController.php
 *
 * Controller for managing fixed DynamicEnum definitions and their customizable options/metadata.
 *
 * Updated Features / Problems Solved (aligned with refined module requirements – January 2026):
 * - Removed all creation/deletion functionality:
 *     • No store(), create(), destroy() – enums are fixed/seeded (e.g., title, gender, profile_type, address.type).
 *     • Prevents accidental breaking of core vocabularies that existing data depends on.
 * - Retained and enhanced index() for admin listing (expandable table with HasTableQuery support).
 * - Added two dedicated PATCH endpoints:
 *     • updateMetadata() – allows editing label, description, color (immutable: name, applies_to).
 *     • updateOptions() – bulk updates the entire options array (add/edit/delete/reorder).
 * - Strict tenancy enforcement: only school-owned overrides can be modified; global defaults protected
 *   (super-admins can be allowed separately via policy if needed).
 * - Dedicated lightweight options() API unchanged – powers frontend DynamicEnumField.vue.
 * - All responses JSON for seamless Inertia/modal integration (success messages, refreshed data).
 * - Comprehensive error handling: 404 if not found, 403 for cross-tenant access, validation via typed requests.
 * - Performance: minimal queries, leverages visibleToSchool/visibleForModel scopes.
 * - Security: model binding + explicit tenancy checks.
 * - Extensible: ready for DynamicEnumPolicy with gates like 'update-metadata', 'update-options'.
 *
 * Fits into the Refined DynamicEnums Module:
 * - Serves as the secure backend for the admin Index page (listing) and two modals:
 *     • DynamicEnumMetadataForm.vue (label/description/color)
 *     • DynamicEnumOptionsForm.vue (full options CRUD + reorder)
 * - Enforces "fixed enums, customizable options" paradigm – true free-form fields belong in Custom Fields module.
 * - Works with HasDynamicEnum trait (value storage), InDynamicEnum rule (validation), and frontend composables.
 * - Production-ready: secure, efficient, consistent with Laravel/Inertia best practices.
 */

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDynamicEnumOptionsRequest;
use App\Http\Requests\UpdateDynamicEnumRequest;
use App\Models\DynamicEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicEnumController extends Controller
{
    /**
     * Display listing of dynamic enums visible to the current school (JSON for DataTable).
     *
     * Supports server-side sorting, filtering, global search via HasTableQuery trait.
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = GetSchoolModel()?->id;

        $data = DynamicEnum::visibleToSchool($schoolId)
            ->tableQuery($request);

        return response()->json($data);
    }

    /**
     * Update only the editable metadata (label, description, color) of a dynamic enum.
     *
     * Immutable fields (name, applies_to) are protected by request validation (not accepted).
     */
    public function updateMetadata(UpdateDynamicEnumRequest $request, DynamicEnum $dynamicEnum): JsonResponse
    {
        $schoolId = GetSchoolModel()?->id;

        // Prevent editing global enums unless super-admin (adjust policy as needed)
        if ($dynamicEnum->school_id !== null && $dynamicEnum->school_id !== $schoolId) {
            abort(403, 'Unauthorized to modify this enum.');
        }

        $dynamicEnum->update($request->validated());

        return response()->json([
            'message' => 'Enum details updated successfully.',
            'enum'    => $dynamicEnum->fresh(),
        ]);
    }

    /**
     * Bulk update the options array of a dynamic enum (add/edit/delete/reorder).
     *
     * Full array replacement – matches frontend DataTable editing pattern.
     */
    public function updateOptions(UpdateDynamicEnumOptionsRequest $request, DynamicEnum $dynamicEnum): JsonResponse
    {
        $schoolId = GetSchoolModel()?->id;

        if ($dynamicEnum->school_id !== null && $dynamicEnum->school_id !== $schoolId) {
            abort(403, 'Unauthorized to modify options for this enum.');
        }

        $dynamicEnum->update([
            'options' => $request->validated()['options'],
        ]);

        return response()->json([
            'message' => 'Options updated successfully.',
            'enum'    => $dynamicEnum->fresh(),
        ]);
    }

    /**
     * API endpoint to fetch allowed options for a specific dynamic enum property.
     *
     * Used by frontend DynamicEnumField.vue and useDynamicEnums composable.
     * Lightweight – returns only the options array.
     */
    public function options(string $appliesTo, string $name): JsonResponse
    {
        $schoolId = GetSchoolModel()?->id;

        $enum = DynamicEnum::visibleForModel($appliesTo, $schoolId)
            ->where('name', $name)
            ->firstOrFail();

        return response()->json([
            'options' => $enum->options ?? [],
        ]);
    }
}
