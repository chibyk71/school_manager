<?php

namespace App\Http\Controllers\FileManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileManager\AttachFileRequest;
use App\Http\Requests\FileManager\UploadFileRequest;
use App\Http\Resources\FileManager\AttachmentResource;
use App\Http\Resources\FileManager\FileResource;
use App\Models\FileManager\File;
use App\Services\FileManager\AttachmentService;
use App\Services\FileManager\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(
        private readonly FileService $files,
        private readonly AttachmentService $attachments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', File::class);

        $schoolId = $this->activeSchoolId();
        $folderId = $request->query('folder_id');
        $folderId = is_string($folderId) && $folderId !== '' ? $folderId : null;
        $standalone = $request->boolean('standalone');

        if ($standalone) {
            $folderId = null;
        }

        try {
            $items = $this->files->listForContext($schoolId, $folderId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => FileResource::collection($items)->resolve(),
        ]);
    }

    public function store(UploadFileRequest $request): JsonResponse
    {
        Gate::authorize('create', File::class);

        try {
            $file = $this->files->upload(
                $request->file('file'),
                $request->safe()->except('file'),
                $this->activeSchoolId()
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'data' => (new FileResource($file))->resolve(),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $file = $this->files->findForContext($id, $this->activeSchoolId());
        if ($file === null) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        Gate::authorize('view', $file);

        return response()->json([
            'data' => (new FileResource($file))->resolve(),
        ]);
    }

    public function download(string $id): StreamedResponse|JsonResponse
    {
        $file = $this->files->findForContext($id, $this->activeSchoolId());
        if ($file === null) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        Gate::authorize('download', $file);

        try {
            $payload = $this->files->retrieve($file, $this->activeSchoolId());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $stream = $payload['stream'];
        $filename = $payload['filename'];
        $mime = $payload['mime'] ?? 'application/octet-stream';

        return response()->streamDownload(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $file = $this->files->findForContext($id, $this->activeSchoolId());
        if ($file === null) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        Gate::authorize('delete', $file);

        try {
            $this->files->delete($file, $this->activeSchoolId());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(null, 204);
    }

    public function move(Request $request, string $id): JsonResponse
    {
        $file = $this->files->findForContext($id, $this->activeSchoolId());
        if ($file === null) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        Gate::authorize('update', $file);

        $folderId = $request->input('folder_id');
        $folderId = is_string($folderId) && $folderId !== '' ? $folderId : null;

        try {
            $file = $this->files->move($file, $folderId, $this->activeSchoolId());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => (new FileResource($file))->resolve(),
        ]);
    }

    public function attach(AttachFileRequest $request, string $id): JsonResponse
    {
        $file = $this->files->findForContext($id, $this->activeSchoolId());
        if ($file === null) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        Gate::authorize('attach', $file);

        try {
            $attachment = $this->attachments->attach(
                $file,
                $request->validated(),
                $this->activeSchoolId()
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => (new AttachmentResource($attachment))->resolve(),
        ], 201);
    }

    public function detach(string $id, string $attachmentId): JsonResponse
    {
        $file = $this->files->findForContext($id, $this->activeSchoolId());
        if ($file === null) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        Gate::authorize('attach', $file);

        $attachment = $file->attachments()->where('id', $attachmentId)->first();
        if ($attachment === null) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        try {
            $this->attachments->detach($attachment, $this->activeSchoolId());
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
