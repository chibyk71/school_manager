<?php

namespace App\Http\Controllers\Settings\Storage;

use App\Enums\StorageProviderOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storage\StoreStorageProviderRequest;
use App\Http\Requests\Storage\TestStorageProviderConnectionRequest;
use App\Http\Requests\Storage\UpdateStorageProviderRequest;
use App\Http\Resources\StorageProviderResource;
use App\Models\StorageProvider;
use App\Services\Storage\StorageProviderConnectionTester;
use App\Services\Storage\StorageProviderDependencyChecker;
use App\Services\Storage\StorageProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Storage provider management (File Manager Phase 1).
 *
 * System-owned providers may only be mutated when there is no active school
 * context (platform/tenant administration). School-owned providers are
 * restricted to the active school.
 */
class StorageProviderController extends Controller
{
    public function __construct(
        private readonly StorageProviderManager $manager,
        private readonly StorageProviderConnectionTester $tester,
        private readonly StorageProviderDependencyChecker $dependencies,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StorageProvider::class);

        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        $schoolId = $school?->id;

        $providers = $this->manager->listForContext($schoolId);

        return Inertia::render('Settings/Storage/Providers', [
            'providers' => StorageProviderResource::collection($providers)->resolve(),
            'canManage' => $request->user()?->hasPermission('storage-providers.manage') ?? false,
            'activeSchoolId' => $schoolId,
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Storage Providers'],
            ],
        ]);
    }

    public function store(StoreStorageProviderRequest $request): JsonResponse
    {
        $this->authorize('create', StorageProvider::class);

        $data = $request->validated();
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        $schoolId = $school?->id;

        $ownership = StorageProviderOwnership::from($data['ownership']);

        if ($ownership === StorageProviderOwnership::System) {
            // System providers only when no school context (or explicit platform path).
            if ($schoolId !== null) {
                // Allow create of system provider only for actors who manage in tenant context
                // without forcing school_id; strip school association.
                $data['school_id'] = null;
            }
        } else {
            if ($schoolId === null) {
                return response()->json([
                    'message' => 'School-owned providers require an active school context.',
                ], 422);
            }
            $data['school_id'] = $schoolId;
        }

        $provider = $this->manager->create($data);

        return response()->json([
            'message' => 'Storage provider created.',
            'provider' => (new StorageProviderResource($provider))->resolve(),
        ], 201);
    }

    public function update(UpdateStorageProviderRequest $request, string $id): JsonResponse
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        $schoolId = $school?->id;

        $provider = $this->manager->findForContext($id, $schoolId);
        if ($provider === null) {
            abort(404);
        }

        $this->authorize('update', $provider);

        if ($provider->isSystemOwned() && $schoolId !== null) {
            // School context must not mutate system-owned configuration.
            return response()->json([
                'message' => 'System-owned storage providers cannot be modified from a school context.',
            ], 403);
        }

        if ($provider->isSchoolOwned() && $provider->school_id !== $schoolId) {
            abort(403);
        }

        $provider = $this->manager->update($provider, $request->validated());

        return response()->json([
            'message' => 'Storage provider updated.',
            'provider' => (new StorageProviderResource($provider))->resolve(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        $provider = $this->manager->findForContext($id, $school?->id);
        if ($provider === null) {
            abort(404);
        }

        $this->authorize('view', $provider);

        return response()->json([
            'provider' => (new StorageProviderResource($provider))->resolve(),
        ]);
    }

    public function testConnection(TestStorageProviderConnectionRequest $request): JsonResponse
    {
        $this->authorize('testConnection', StorageProvider::class);

        $result = $this->tester->test($request->validated());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function dependencies(Request $request, string $id): JsonResponse
    {
        $school = function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        $provider = $this->manager->findForContext($id, $school?->id);
        if ($provider === null) {
            abort(404);
        }

        $this->authorize('checkDependencies', $provider);

        return response()->json($this->dependencies->check($provider));
    }
}
