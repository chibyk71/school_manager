<?php

/**
 * Dynamic Enum Phase 4 — Inertia administration controller.
 *
 * Scope is resolved from the authenticated context (GetSchoolModel), never from
 * a client-supplied school_id for mutations.
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
     * Definition catalogue (tenant definitions only: school_id IS NULL).
     *
     * Inertia page load returns initialData + columns for AdvancedDataTable.
     * Axios refetch (wantsJson) returns the same tableQuery payload as JSON.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        Gate::authorize('viewAny', DynamicEnum::class);

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

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return Inertia::render('Settings/System/DynamicEnums/Index', [
            'initialData' => $result['data'],
            'totalRecords' => $result['totalRecords'],
            'columns' => $result['columns'],
            'globalFilterables' => $result['globalFilterables'] ?? [],
            'canManage' => Gate::allows('manage', DynamicEnum::class),
            'canManageGlobals' => Gate::allows('manageGlobals', DynamicEnum::class),
        ]);
    }

    public function show(string $key): InertiaResponse
    {
        Gate::authorize('view', DynamicEnum::class);

        $school = GetSchoolModel();
        $canManage = Gate::allows('manage', DynamicEnum::class);
        $canManageGlobals = Gate::allows('manageGlobals', DynamicEnum::class);

        try {
            $detail = $this->admin->detail(
                $key,
                $school,
                $canManage,
                $canManageGlobals
            );
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        }

        return Inertia::render('Settings/System/DynamicEnums/Show', [
            'detail' => $detail,
            'canManage' => $canManage,
            'canManageGlobals' => $canManageGlobals,
            'hasSchoolContext' => $school !== null,
        ]);
    }

    public function updateDefinition(UpdateDefinitionPresentationRequest $request, string $key): RedirectResponse
    {
        $school = GetSchoolModel();

        // tenant is an HTTP operation selector only — never part of the domain payload.
        $payload = array_intersect_key(
            $request->validated(),
            array_flip(['label', 'description'])
        );

        try {
            if ($request->boolean('tenant') || $school === null) {
                Gate::authorize('manageGlobals', DynamicEnum::class);
                $this->admin->updateTenantDefinitionPresentation($key, $payload);
            } else {
                Gate::authorize('manage', DynamicEnum::class);
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

        try {
            if ($request->boolean('tenant') || $school === null) {
                Gate::authorize('manageGlobals', DynamicEnum::class);
                $this->admin->createTenantOption($key, $request->validated());
            } else {
                Gate::authorize('manage', DynamicEnum::class);
                $this->admin->createSchoolOption($school, $key, $request->validated());
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

        try {
            if ($option->school_id === null) {
                Gate::authorize('manageGlobals', DynamicEnum::class);
                $this->admin->updateTenantOptionPresentation($key, $option, $request->validated());
            } else {
                Gate::authorize('manage', DynamicEnum::class);
                $this->admin->updateSchoolOptionPresentation($school, $key, $option, $request->validated());
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

        try {
            if ($option->school_id === null) {
                Gate::authorize('manageGlobals', DynamicEnum::class);
                $this->admin->activateTenantOption($key, $option);
            } else {
                Gate::authorize('manage', DynamicEnum::class);
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

        try {
            if ($option->school_id === null) {
                Gate::authorize('manageGlobals', DynamicEnum::class);
                $this->admin->deactivateTenantOption($key, $option);
            } else {
                Gate::authorize('manage', DynamicEnum::class);
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
        Gate::authorize('manageGlobals', DynamicEnum::class);

        try {
            $this->admin->makeOptionRequired($key, $option);
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        } catch (ValidationException $e) {
            throw $e;
        }

        return back();
    }

    public function removeRequired(string $key, DynamicEnumOption $option): RedirectResponse
    {
        Gate::authorize('manageGlobals', DynamicEnum::class);

        try {
            $this->admin->removeOptionRequired($key, $option);
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

        try {
            $this->admin->resetSchoolOverride($school, $key, $option);
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

        try {
            if ($option->school_id === null) {
                Gate::authorize('manageGlobals', DynamicEnum::class);
                $this->admin->deleteTenantOption($key, $option);
            } else {
                Gate::authorize('manage', DynamicEnum::class);
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
