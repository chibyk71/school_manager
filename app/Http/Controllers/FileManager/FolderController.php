<?php

namespace App\Http\Controllers\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileManager\StoreFolderRequest;
use App\Http\Requests\FileManager\UpdateFolderRequest;
use App\Http\Resources\FileManager\FolderResource;
use App\Models\FileManager\Folder;
use App\Services\FileManager\FolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class FolderController extends Controller
{
    public function __construct(
        private readonly FolderService $folders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Folder::class);

        $schoolId = $this->activeSchoolId();
        $parentId = $request->query('parent_id');
        $parentId = is_string($parentId) && $parentId !== '' ? $parentId : null;

        try {
            $items = $this->folders->listForContext($schoolId, $parentId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => FolderResource::collection($items)->resolve(),
        ]);
    }

    public function store(StoreFolderRequest $request): JsonResponse
    {
        Gate::authorize('create', Folder::class);

        try {
            $folder = $this->folders->create($request->validated(), $this->activeSchoolId());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => (new FolderResource($folder))->resolve(),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $folder = $this->folders->findForContext($id, $this->activeSchoolId());
        if ($folder === null) {
            return response()->json(['message' => 'Folder not found.'], 404);
        }

        Gate::authorize('view', $folder);

        return response()->json([
            'data' => (new FolderResource($folder))->resolve(),
        ]);
    }

    public function update(UpdateFolderRequest $request, string $id): JsonResponse
    {
        $folder = $this->folders->findForContext($id, $this->activeSchoolId());
        if ($folder === null) {
            return response()->json(['message' => 'Folder not found.'], 404);
        }

        Gate::authorize('update', $folder);

        $data = $request->validated();
        $schoolId = $this->activeSchoolId();

        try {
            if (array_key_exists('parent_id', $data)) {
                $folder = $this->folders->move($folder, $data['parent_id'], $schoolId);
                unset($data['parent_id']);
            }
            if ($data !== []) {
                $folder = $this->folders->update($folder, $data, $schoolId);
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => (new FolderResource($folder))->resolve(),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $folder = $this->folders->findForContext($id, $this->activeSchoolId());
        if ($folder === null) {
            return response()->json(['message' => 'Folder not found.'], 404);
        }

        Gate::authorize('delete', $folder);

        try {
            $this->folders->delete($folder, $this->activeSchoolId());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(null, 204);
    }

    private function activeSchoolId(): ?string
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;

        return $school?->id;
    }
}
