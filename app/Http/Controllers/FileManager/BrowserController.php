<?php

namespace App\Http\Controllers\FileManager;

use App\Enums\FileManager\Capability;
use App\Http\Controllers\Controller;
use App\Http\Resources\FileManager\FileResource;
use App\Http\Resources\FileManager\FolderResource;
use App\Models\FileManager\Folder;
use App\Services\FileManager\FileService;
use App\Services\FileManager\FolderService;
use App\Services\Storage\StorageProviderManager;
use App\Support\FileManager\FolderCapabilityResolver;
use App\Support\FileManager\ModuleDefinitionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administrator browser for File Manager folder/file workflows (Phase 2–4).
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

        $searchRaw = $request->query('q');
        $search = is_string($searchRaw) ? trim(mb_substr($searchRaw, 0, 100)) : '';
        if ($search === '') {
            $search = null;
        }

        $managedByFilter = $request->query('managed_by');
        $managedByFilter = is_string($managedByFilter) && in_array($managedByFilter, ['file_manager', 'module'], true)
            ? $managedByFilter
            : null;

        $current = null;
        if ($parentId !== null) {
            $current = $this->folders->findForContext($parentId, $schoolId);
            if ($current === null) {
                abort(404);
            }
            try {
                FolderCapabilityResolver::assertAllows(
                    $current,
                    Capability::Browse
                );
            } catch (\InvalidArgumentException $e) {
                abort(403, $e->getMessage());
            }
        }

        try {
            $folderItems = $this->folders->listForContext($schoolId, $parentId, $search);
            $fileItems = $this->files->listForContext(
                $schoolId,
                $parentId,
                $search,
                100,
                $managedByFilter,
            );
        } catch (\InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        $providers = $this->providers->listForContext($schoolId);
        $user = $request->user();
        $crumbs = $this->buildCrumbs($current, $schoolId);

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
            'search' => $search,
            'managedByFilter' => $managedByFilter,
            'crumbs' => $crumbs,
        ]);
    }

    /**
     * @return list<array{label: string, href?: string}>
     */
    private function buildCrumbs(?Folder $current, ?string $schoolId): array
    {
        $crumbs = [
            [
                'label' => 'File Manager',
                'href' => route('file-manager.browser'),
            ],
        ];

        if ($current === null) {
            return $crumbs;
        }

        $ancestors = [];
        $walk = $current;
        $guard = 0;
        while ($walk !== null && $guard < 50) {
            $ancestors[] = $walk;
            if ($walk->parent_id === null) {
                break;
            }
            $walk = $this->folders->findForContext($walk->parent_id, $schoolId);
            $guard++;
        }

        foreach (array_reverse($ancestors) as $folder) {
            $crumbs[] = [
                'label' => $folder->name,
                'href' => route('file-manager.browser', ['folder' => $folder->id]),
            ];
        }

        return $crumbs;
    }

    private function activeSchoolId(): ?string
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;

        return $school?->id;
    }
}
