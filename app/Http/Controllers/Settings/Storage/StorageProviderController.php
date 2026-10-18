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
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Storage provider management (File Manager Phase 1).
 *
 * Authorization boundaries:
 * - School context (active school): school-owned providers for that school only.
 * - Platform context (no active school): system-owned providers only.
 * - School users cannot view, create, update, or test system-owned providers.
 * - file_manager.allow_school_provider_management gates school-owned mutations.
 */
class StorageProviderController extends Controller
{
    public function __construct(
        private readonly StorageProviderManager $manager,
        private readonly StorageProviderConnectionTester $tester,
        private readonly StorageProviderDependencyChecker $dependencies,
        private readonly SettingsResolver $settings,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StorageProvider::class);

        $schoolId = $this->activeSchoolId();
        $providers = $this->manager->listForContext($schoolId);

        $defaultDriver = 'local';
        if ($schoolId !== null) {
            try {
                $pref = $this->settings->get(
                    'file_manager.default_provider_preference',
                    SettingsScope::school($schoolId)
                );
                if (is_string($pref) && in_array($pref, ['local', 's3'], true)) {
                    $defaultDriver = $pref;
                }
            } catch (\Throwable) {
                // Registry default applies.
            }
        }

        return Inertia::render('Settings/Storage/Providers', [
            'providers' => StorageProviderResource::collection($providers)->resolve(),
            'canManage' => $request->user()?->hasPermission('storage-providers.manage') ?? false,
            'activeSchoolId' => $schoolId,
            'defaultDriver' => $defaultDriver,
            'schoolProviderManagementAllowed' => $this->schoolProviderManagementAllowed($schoolId),
            'crumbs' => [
                ['label' => 'Settings'],
                ['label' => 'Storage Providers'],
            ],
        ]);
    }

    public function store(StoreStorageProviderRequest $request): JsonResponse
    {
        Gate::authorize('create', StorageProvider::class);

        $data = $request->validated();
        $schoolId = $this->activeSchoolId();
        $ownership = StorageProviderOwnership::from($data['ownership']);

        if (! $this->manager->canMutateOwnership($schoolId, $ownership)) {
            return response()->json([
                'message' => $ownership === StorageProviderOwnership::System
                    ? 'System-owned storage providers cannot be created from a school context.'
                    : 'School-owned storage providers require an active school context.',
            ], 403);
        }

        if ($ownership === StorageProviderOwnership::School
            && ! $this->schoolProviderManagementAllowed($schoolId)) {
            return response()->json([
                'message' => 'School-level storage provider management is disabled for this school.',
            ], 403);
        }

        unset($data['school_id']);

        try {
            $provider = $this->manager->create($data, $schoolId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'message' => 'Storage provider created.',
            'provider' => (new StorageProviderResource($provider))->resolve(),
        ], 201);
    }

    public function update(UpdateStorageProviderRequest $request, string $id): JsonResponse
    {
        $schoolId = $this->activeSchoolId();
        $provider = $this->manager->findForContext($id, $schoolId);
        if ($provider === null) {
            abort(404);
        }

        Gate::authorize('update', $provider);

        if ($provider->isSchoolOwned()
            && ! $this->schoolProviderManagementAllowed($schoolId)) {
            return response()->json([
                'message' => 'School-level storage provider management is disabled for this school.',
            ], 403);
        }

        try {
            $provider = $this->manager->update($provider, $request->validated(), $schoolId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'message' => 'Storage provider updated.',
            'provider' => (new StorageProviderResource($provider))->resolve(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $schoolId = $this->activeSchoolId();
        $provider = $this->manager->findForContext($id, $schoolId);
        if ($provider === null) {
            abort(404);
        }

        Gate::authorize('view', $provider);

        return response()->json([
            'provider' => (new StorageProviderResource($provider))->resolve(),
        ]);
    }

    public function testConnection(TestStorageProviderConnectionRequest $request): JsonResponse
    {
        Gate::authorize('testConnection', StorageProvider::class);

        $schoolId = $this->activeSchoolId();
        $data = $request->validated();

        $ownershipValue = $data['ownership'] ?? ($schoolId !== null ? 'school' : 'system');
        $ownership = StorageProviderOwnership::from($ownershipValue);

        if (! $this->manager->canMutateOwnership($schoolId, $ownership)) {
            return response()->json([
                'message' => 'Connection testing for this ownership scope is not permitted in the current context.',
            ], 403);
        }

        if ($ownership === StorageProviderOwnership::School
            && ! $this->schoolProviderManagementAllowed($schoolId)) {
            return response()->json([
                'message' => 'School-level storage provider management is disabled for this school.',
            ], 403);
        }

        // Connection tests do not persist providers, but the shared validator still
        // enforces school_id for school ownership. Bind the active school context
        // so school-scoped probes validate the same way as system-scoped ones.
        if ($ownership === StorageProviderOwnership::School && $schoolId !== null) {
            $data['school_id'] = $schoolId;
        }
        if ($ownership === StorageProviderOwnership::System) {
            unset($data['school_id']);
        }

        $result = $this->tester->test($data);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function dependencies(Request $request, string $id): JsonResponse
    {
        $schoolId = $this->activeSchoolId();
        $provider = $this->manager->findForContext($id, $schoolId);
        if ($provider === null) {
            abort(404);
        }

        Gate::authorize('checkDependencies', $provider);

        return response()->json($this->dependencies->check($provider));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $schoolId = $this->activeSchoolId();
        $provider = $this->manager->findForContext($id, $schoolId);
        if ($provider === null) {
            abort(404);
        }

        Gate::authorize('delete', $provider);

        if ($provider->isSchoolOwned() && ! $this->schoolProviderManagementAllowed($schoolId)) {
            return response()->json([
                'message' => 'School-level storage provider management is disabled for this school.',
            ], 403);
        }

        try {
            $this->manager->delete($provider, $schoolId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(null, 204);
    }

    private function activeSchoolId(): ?string
    {
        if (! function_exists('GetSchoolModel')) {
            return null;
        }

        $school = GetSchoolModel();

        return $school?->id;
    }

    /**
     * Whether school-owned provider mutations are allowed for this school.
     *
     * Fail-closed: if Settings resolution throws, management is denied.
     * An explicit stored false is always enforced. Missing row uses registry default (true).
     */
    private function schoolProviderManagementAllowed(?string $schoolId): bool
    {
        if ($schoolId === null) {
            return true;
        }

        try {
            $value = $this->settings->get(
                'file_manager.allow_school_provider_management',
                SettingsScope::school($schoolId)
            );

            return (bool) $value;
        } catch (\Throwable) {
            return false;
        }
    }
}
