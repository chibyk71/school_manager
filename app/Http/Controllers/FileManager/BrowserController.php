<?php

namespace App\Http\Controllers\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Resources\FileManager\FileResource;
use App\Http\Resources\FileManager\FolderResource;
use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Services\FileManager\FileService;
use App\Services\FileManager\FolderService;
use App\Services\Storage\StorageProviderManager;
use App\Support\FileManager\ModuleDefinitionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Minimal functional browser UI for Phase 2 folder/file workflows.
 */
class BrowserController extends Controller
{
    public function __construct(
        private readonly FolderService $folders,
        private readonly FileService $files,
        private readonly StorageProviderManager $providers,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Folder::class);

        $schoolId = $this->activeSchoolId();
        $parentId = $request->query('folder');
        $parentId = is_string($parentId) && $parentId !== '' ? $parentId : null;

        $current = null;
        if ($parentId !== null) {
            $current = $this->folders->findForContext($parentId, $schoolId);
            if ($current === null) {
                abort(404);
            }
        }

        $folderItems = $this->folders->listForContext($schoolId, $parentId);
        $fileItems = $this->files->listForContext($schoolId, $parentId);
        $providers = $this->providers->listForContext($schoolId);

        $user = $request->user();

        return Inertia::render('FileManager/Browser', [
            'currentFolder' => $current ? (new FolderResource($current))->resolve() : null,
            'folders' => FolderResource::collection($folderItems)->resolve(),
            'files' => FileResource::collection($fileItems)->resolve(),
            'providers' => $providers->map(static fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'driver' => $p->driver instanceof \BackedEnum ? $p->driver->value : $p->driver,
                'enabled' => $p->enabled,
            ])->values()->all(),
            'modules' => collect(ModuleDefinitionRegistry::active())->map(static fn ($d) => [
                'code' => $d->code,
                'label' => $d->label,
            ])->values()->all(),
            'canManage' => $user?->hasPermission('file-manager.manage') ?? false,
            'canAttach' => $user?->hasPermission('file-manager.attach') ?? false,
            'canConfigure' => $user?->hasPermission('file-manager.configure') ?? false,
            'activeSchoolId' => $schoolId,
            'crumbs' => [
                ['label' => 'File Manager', 'href' => route('file-manager.browser')],
                ...($current ? [['label' => $current->name]] : []),
            ],
        ]);
    }

    private function activeSchoolId(): ?string
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;

        return $school?->id;
    }
}
