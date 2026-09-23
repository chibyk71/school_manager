<?php

/**
 * Dynamic Enum Phase 4 — Inertia administration controller.
 *
 * Scope is resolved from the authenticated context (GetSchoolModel), never from
 * a client-supplied school_id for mutations.
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DynamicEnumsController extends Controller
{
    public function __construct(
        private readonly DynamicEnumAdministrationService $admin,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DynamicEnum::class);

        $definitions = $this->admin->catalogue()->map(fn (DynamicEnum $d) => [
            'id' => $d->id,
            'key' => $d->key,
            'label' => $d->label,
            'description' => $d->description,
        ]);

        return Inertia::render('Settings/System/DynamicEnums/Index', [
            'definitions' => $definitions,
            'canManage' => Gate::allows('manage', DynamicEnum::class),
            'canManageGlobals' => Gate::allows('manageGlobals', DynamicEnum::class),
        ]);
    }

    public function show(string $key): Response
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

        try {
            if ($request->boolean('tenant') || $school === null) {
                Gate::authorize('manageGlobals', DynamicEnum::class);
                $this->admin->updateTenantDefinitionPresentation($key, $request->validated());
            } else {
                Gate::authorize('manage', DynamicEnum::class);
                $this->admin->updateSchoolDefinitionPresentation($school, $key, $request->validated());
            }
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        }

        return back()->with('success', 'Definition presentation updated.');
    }

    public function storeOption(StoreOptionRequest $request, string $key): RedirectResponse
    {
        $data = $request->validated();
        $school = GetSchoolModel();
        $mode = $data['mode'] ?? 'option';
        $attributes = array_intersect_key($data, array_flip(['sort_order', 'color', 'icon', 'is_active', 'is_required']));

        try {
            if ($request->boolean('tenant') || $school === null) {
                Gate::authorize('manageGlobals', DynamicEnum::class);
                $this->admin->createTenantOption($key, $data['value'], $data['label'], $attributes);
            } else {
                Gate::authorize('manage', DynamicEnum::class);
                unset($attributes['is_required']);
                if ($mode === 'override') {
                    $this->admin->createSchoolOverride($school, $key, $data['value'], $data['label'], $attributes);
                } else {
                    $this->admin->createSchoolOption($school, $key, $data['value'], $data['label'], $attributes);
                }
            }
        } catch (DynamicEnumNotConfiguredException $e) {
            abort(404, $e->getMessage());
        }

        return back()->with('success', 'Option created.');
    }

    public function updateOption(UpdateOptionPresentationRequest $request, string $key, DynamicEnumOption $option): RedirectResponse
    {
        $school = GetSchoolModel();

        if ($option->isTenantOption()) {
            Gate::authorize('manageGlobals', DynamicEnum::class);
            $this->admin->updateTenantOption($option, $request->validated());
        } else {
            Gate::authorize('manage', DynamicEnum::class);
            if ($school === null || $option->school_id !== $school->id) {
                abort(403, 'Cannot modify another school\'s option.');
            }
            $this->admin->updateSchoolOption($school, $option, $request->validated());
        }

        return back()->with('success', 'Option updated.');
    }

    public function activateOption(string $key, DynamicEnumOption $option): RedirectResponse
    {
        $school = GetSchoolModel();

        if ($option->isTenantOption()) {
            Gate::authorize('manageGlobals', DynamicEnum::class);
            $this->admin->activateTenantOption($option);
        } else {
            Gate::authorize('manage', DynamicEnum::class);
            if ($school === null || $option->school_id !== $school->id) {
                abort(403);
            }
            $this->admin->activateSchoolOption($school, $option);
        }

        return back()->with('success', 'Option activated.');
    }

    public function deactivateOption(string $key, DynamicEnumOption $option): RedirectResponse
    {
        $school = GetSchoolModel();

        if ($option->isTenantOption()) {
            Gate::authorize('manageGlobals', DynamicEnum::class);
            $this->admin->deactivateTenantOption($option);
        } else {
            Gate::authorize('manage', DynamicEnum::class);
            if ($school === null || $option->school_id !== $school->id) {
                abort(403);
            }
            $this->admin->deactivateSchoolOption($school, $option);
        }

        return back()->with('success', 'Option deactivated.');
    }

    public function makeRequired(string $key, DynamicEnumOption $option): RedirectResponse
    {
        Gate::authorize('manageGlobals', DynamicEnum::class);
        $this->admin->makeTenantOptionRequired($option);

        return back()->with('success', 'Option marked required.');
    }

    public function removeRequired(string $key, DynamicEnumOption $option): RedirectResponse
    {
        Gate::authorize('manageGlobals', DynamicEnum::class);
        $this->admin->removeTenantOptionRequired($option);

        return back()->with('success', 'Option requiredness removed.');
    }

    public function resetOverride(string $key, DynamicEnumOption $option): RedirectResponse
    {
        Gate::authorize('manage', DynamicEnum::class);
        $school = GetSchoolModel();
        if ($school === null || $option->school_id !== $school->id) {
            abort(403);
        }
        $this->admin->removeSchoolOverride($school, $option);

        return back()->with('success', 'Override reset; tenant configuration is effective again.');
    }

    public function destroyOption(string $key, DynamicEnumOption $option): RedirectResponse
    {
        $school = GetSchoolModel();

        try {
            if ($option->isTenantOption()) {
                Gate::authorize('manageGlobals', DynamicEnum::class);
                $this->admin->deleteTenantOption($option);
            } else {
                Gate::authorize('manage', DynamicEnum::class);
                if ($school === null || $option->school_id !== $school->id) {
                    abort(403);
                }
                $this->admin->deleteSchoolOption($school, $option);
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Option permanently deleted.');
    }
}
