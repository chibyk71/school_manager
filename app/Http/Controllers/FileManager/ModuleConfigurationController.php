<?php

namespace App\Http\Controllers\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileManager\ConfigureModuleRequest;
use App\Services\FileManager\ModuleConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ModuleConfigurationController extends Controller
{
    public function __construct(
        private readonly ModuleConfigurationService $modules,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->hasPermission('file-manager.configure')) {
            abort(403, 'You do not have permission to view File Manager module configuration.');
        }

        $schoolId = $this->requireSchoolId();
        $items = $this->modules->listForSchool($schoolId);

        return response()->json([
            'data' => $items->map(static function (array $row) {
                $definition = $row['definition'];
                $config = $row['configuration'];

                return [
                    'code' => $definition->code,
                    'label' => $definition->label,
                    'description' => $definition->description,
                    'max_capabilities' => $definition->maxCapabilityValues(),
                    'retired' => $definition->retired,
                    'enabled' => $config?->enabled ?? true,
                    'settings' => $config?->settings,
                    'configured' => $config !== null,
                ];
            })->values()->all(),
        ]);
    }

    public function update(ConfigureModuleRequest $request, string $moduleCode): JsonResponse
    {
        if (! $request->user()?->hasPermission('file-manager.configure')) {
            abort(403, 'You do not have permission to configure File Manager modules.');
        }

        $schoolId = $this->requireSchoolId();

        try {
            $config = $this->modules->configure($schoolId, $moduleCode, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'module_code' => $config->module_code,
                'enabled' => $config->enabled,
                'settings' => $config->settings,
            ],
        ]);
    }

    private function requireSchoolId(): string
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        if ($school === null) {
            abort(403, 'School context is required to configure File Manager modules.');
        }

        return $school->id;
    }
}
