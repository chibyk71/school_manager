<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConfigRequest;
use App\Http\Requests\UpdateConfigRequest;
use App\Models\Configuration\Config;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ConfigController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        Gate::authorize('viewany', Config::class);

        $school = GetSchoolModel();

        $configs = Config::visibleToSchool($school?->id)
            ->tableQuery($request);

        return Inertia::render('Settings/Configurations/Index', [
            'configs' => $configs,
        ]);
    }

    public function store(StoreConfigRequest $request): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('create', Config::class);

        $data    = $request->validated();
        $school  = GetSchoolModel();

        Config::create([
            'name'        => $data['name'],
            'applies_to'  => $data['applies_to'],
            'label'       => $data['label'],
            'description' => $data['description'] ?? null,
            'color'       => $data['color'] ?? null,
            'options'     => $data['options'] ?? [],
            'school_id'    => $school?->id,
        ]);

        return redirect()
            ->route('configs.index')
            ->with('success', 'Configuration created.');
    }

    public function show(Config $config): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('view', $config);

        return response()->json([
            'config' => $config->load('scopeModel'),
        ]);
    }

    public function update(UpdateConfigRequest $request, Config $config): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('update', $config);

        $data   = $request->validated();
        $school = GetSchoolModel();

        $config->update([
            'label'       => $data['label'],
            'description' => $data['description'] ?? null,
            'color'       => $data['color'] ?? null,
            'options'     => $data['options'] ?? [],
            'school_id'    => $school?->id,
        ]);

        return redirect()
            ->route('configs.index')
            ->with('success', 'Configuration updated.');
    }
}