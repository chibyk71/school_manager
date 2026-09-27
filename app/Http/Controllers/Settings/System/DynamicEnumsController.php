<?php

/**
 * Dynamic Enum Phase 7 — Inertia administration controller.
 *
 * Scope is resolved from the authenticated context (GetSchoolModel), never from
 * a client-supplied school_id for mutations.
 *
 * Capabilities are scope-neutral (dynamic-enums.view / dynamic-enums.manage).
 * Application context decides tenant vs school mutation targets:
 *   - no school context → tenant baseline operations
 *   - school context → school overlay / school-only operations
 *
 * Option mutations always pass the route {key} into the administration service so
 * the option's dynamic_enum_id is verified against that definition.
 */

namespace App\Http\Controllers\Settings\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DynamicEnum\StoreOptionRequest;
use App\Http\Requests\Settings\DynamicEnum\UpdateDefinitionPresentationRequest;
use App\Http\Requests\Settings\DynamicEnum\UpdateOptionPresentationRequest;
use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use App\Services\DynamicEnum\DynamicEnumAdministrationService;
use App\Services\DynamicEnum\DynamicEnumNotConfiguredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DynamicEnumsController extends Controller
{
    public function __construct(
        private readonly DynamicEnumAdministrationService $admin,
    ) {
    }

    /**
     * Catalogue:
     *   - tenant context → application definitions (tenant configuration)
     *   - school context → effective school configuration (inherited / overridden /
     *     school-created options once each; no duplicate tenant+school rows)
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        Gate::authorize('viewAny', DynamicEnum::class);

        $school = GetSchoolModel();
        $canManage = Gate::allows('manage', DynamicEnum::class);

        if ($school !== null) {
            $definitions = $this->admin->effectiveSchoolCatalogue($school);

            $payload = [
                'scope' => 'school',
                'school_id' => $school->id,
                'data' => $definitions,
                'columns' => [],
                'meta' => [
                    'currentPage' => 1,
                    'perPage' => count($definitions),
                    'total' => count($definitions),
                    'lastPage' => 1,
                ],
                'canManage' => $canManage,
                'hasSchoolContext' => true,
            ];

            if ($request->wantsJson()) {
                return response()->json($payload);
            }

            return Inertia::render('Settings/System/DynamicEnums/Index', $payload);
        }

        $result = DynamicEnum::query()
            ->whereNull('school_id')
            ->tableQuery($request, [
                'key' => [
                    'header' => 'Key',
                    'sortable' => true,
                    'filterable' => true,
                    'filterType' => 'text',
                ],
                'label' => [
                    'header' => 'Label',
                    'sortable' => true,
                    'filterable' => true,
                    'filterType' => 'text',
                ],
                'description' => [
                    'header' => 'Description',
                    'sortable' => false,
                    'filterable' => true,
                    'filterType' => 'text',
                ],
            ]);

        $payload = [
            'scope' => 'tenant',
            'school_id' => null,
            'data' => $result['data'],
            'columns' => $result['columns'],
            'meta' => $result['meta'] ?? [
                'currentPage' => $result['currentPage'] ?? 1,
                'perPage' => $result['perPage'] ?? 15,
                'total' => $result['totalRecords'] ?? 0,
                'lastPage' => $result['lastPage'] ?? 1,
            ],
            'canManage' => $canManage,
            'hasSchoolContext' => false,
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Settings/System/DynamicEnums/Index', $payload);
    }

    public function show(string $key): InertiaResponse
    {
        Gate::authorize('view', DynamicEnum::class);

        $school = GetSchoolModel();
        $canManage = Gate::allows('manage', DynamicEnum::class);

        try {
            $detail = $this->admin->detail($key, $school, $canManage);
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        }

        return Inertia::render('Settings/System/DynamicEnums/Show', [
            'detail' => $detail,
            'canManage' => $canManage,
            'hasSchoolContext' => $school !== null,
        ]);
    }

    public function updateDefinition(UpdateDefinitionPresentationRequest $request, string $key): RedirectResponse
    {
        $school = GetSchoolModel();
        Gate::authorize('manage', DynamicEnum::class);

        $payload = array_intersect_key(
            $request->validated(),
            array_flip(['label', 'description'])
        );

        try {
            if ($school === null) {
                $this->admin->updateTenantDefinitionPresentation($key, $payload);
            } else {
                $this->admin->updateSchoolDefinitionPresentation($school, $key, $payload);
            }
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        }

        return back();
    }

    public function storeOption(StoreOptionRequest $request, string $key): RedirectResponse
    {
        $school = GetSchoolModel();
        Gate::authorize('manage', DynamicEnum::class);

        $validated = $request->validated();
        $value = (string) $validated['value'];
        $label = (string) $validated['label'];
        $attributes = array_intersect_key(
            $validated,
            array_flip(['sort_order', 'color', 'icon', 'is_active', 'is_required'])
        );
        $mode = $validated['mode'] ?? 'option';
        $wantTenant = $request->boolean('tenant') || $school === null;

        try {
            if ($wantTenant) {
                if ($school !== null) {
                    abort(403, 'Tenant Dynamic Enum mutations require tenant application context.');
                }
                $this->admin->createTenantOption($key, $value, $label, $attributes);
            } else {
                if ($school === null) {
                    abort(403, 'School Dynamic Enum mutations require school application context.');
                }
                if ($mode === 'override') {
                    $this->admin->createSchoolOverride($school, $key, $value, $label, $attributes);
                } else {
                    $this->admin->createSchoolOption($school, $key, $value, $label, $attributes);
                }
            }
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        }

        return back();
    }

    public function updateOption(UpdateOptionPresentationRequest $request, string $key, DynamicEnumOption $option): RedirectResponse
    {
        $school = GetSchoolModel();
        Gate::authorize('manage', DynamicEnum::class);

        try {
            if ($option->school_id === null) {
                if ($school !== null) {
                    abort(403, 'Tenant Dynamic Enum mutations require tenant application context.');
                }
                $this->admin->updateTenantOption($key, $option, $request->validated());
            } else {
                if ($school === null) {
                    abort(403, 'School Dynamic Enum mutations require school application context.');
                }
                $this->admin->updateSchoolOption($school, $key, $option, $request->validated());
            }
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        }

        return back();
    }

    public function activateOption(string $key, DynamicEnumOption $option): RedirectResponse
    {
        $school = GetSchoolModel();
        Gate::authorize('manage', DynamicEnum::class);

        try {
            if ($option->school_id === null) {
                if ($school !== null) {
                    abort(403, 'Tenant Dynamic Enum mutations require tenant application context.');
                }
                $this->admin->activateTenantOption($key, $option);
            } else {
                if ($school === null) {
                    abort(403, 'School Dynamic Enum mutations require school application context.');
                }
                $this->admin->activateSchoolOption($school, $key, $option);
            }
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        }

        return back();
    }

    public function deactivateOption(string $key, DynamicEnumOption $option): RedirectResponse
    {
        $school = GetSchoolModel();
        Gate::authorize('manage', DynamicEnum::class);

        try {
            if ($option->school_id === null) {
                if ($school !== null) {
                    abort(403, 'Tenant Dynamic Enum mutations require tenant application context.');
                }
                $this->admin->deactivateTenantOption($key, $option);
            } else {
                if ($school === null) {
                    abort(403, 'School Dynamic Enum mutations require school application context.');
                }
                $this->admin->deactivateSchoolOption($school, $key, $option);
            }
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        }

        return back();
    }

    public function makeRequired(string $key, DynamicEnumOption $option): RedirectResponse
    {
        Gate::authorize('manage', DynamicEnum::class);

        // Requiredness is tenant-baseline only and requires tenant application context.
        if (GetSchoolModel() !== null) {
            abort(403, 'Tenant Dynamic Enum mutations require tenant application context.');
        }

        try {
            $this->admin->makeTenantOptionRequired($key, $option);
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        }

        return back();
    }

    public function removeRequired(string $key, DynamicEnumOption $option): RedirectResponse
    {
        Gate::authorize('manage', DynamicEnum::class);

        if (GetSchoolModel() !== null) {
            abort(403, 'Tenant Dynamic Enum mutations require tenant application context.');
        }

        try {
            $this->admin->removeTenantOptionRequired($key, $option);
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        }

        return back();
    }

    public function resetOverride(string $key, DynamicEnumOption $option): RedirectResponse
    {
        $school = GetSchoolModel();
        Gate::authorize('manage', DynamicEnum::class);

        if ($school === null) {
            abort(403, 'School Dynamic Enum mutations require school application context.');
        }

        try {
            $this->admin->removeSchoolOverride($school, $key, $option);
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        }

        return back();
    }

    public function destroyOption(string $key, DynamicEnumOption $option): RedirectResponse
    {
        $school = GetSchoolModel();
        Gate::authorize('manage', DynamicEnum::class);

        try {
            if ($option->school_id === null) {
                if ($school !== null) {
                    abort(403, 'Tenant Dynamic Enum mutations require tenant application context.');
                }
                $this->admin->deleteTenantOption($key, $option);
            } else {
                if ($school === null) {
                    abort(403, 'School Dynamic Enum mutations require school application context.');
                }
                $this->admin->deleteSchoolOption($school, $key, $option);
            }
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        }

        return back();
    }
}
