<?php

namespace Tests\Feature\FileManager;

use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureCurrentSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\FileManager\File;
use App\Models\Permission;
use App\Models\School;
use App\Models\StorageProvider;
use App\Models\User;
use App\Services\FileManager\FileLifecycleService;
use App\Services\FileManager\FileService;
use App\Services\FileManager\FolderService;
use App\Support\FileManager\ModuleDefinitionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ReflectionObject;
use Tests\Support\FileManagerTestSchema;
use Tests\TestCase;

/**
 * HTTP contract tests for Phase 3 restore and purge-failure recovery.
 */
class FileManagerPhase3HttpTest extends TestCase
{
    private string $localRoot;

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        FileManagerTestSchema::migrate();
        ModuleDefinitionRegistry::flush();

        $gate = Gate::getFacadeRoot();
        $ref = new ReflectionObject($gate);
        $prop = $ref->getProperty('beforeCallbacks');
        $prop->setAccessible(true);
        $prop->setValue($gate, []);

        $this->withoutMiddleware([
            HandleInertiaRequests::class,
            EnsureCurrentSession::class,
            CheckMaintenanceMode::class,
            VerifyCsrfToken::class,
            ValidateCsrfToken::class,
        ]);
        $this->withoutVite();

        $this->localRoot = storage_path('app/testing/fm-p3-http-'.Str::uuid()->toString());
        if (! is_dir($this->localRoot)) {
            mkdir($this->localRoot, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->localRoot)) {
            $this->deleteTree($this->localRoot);
        }
        Model::reguard();
        parent::tearDown();
    }

    public function test_restore_http_omitted_folder_id_does_not_restore_to_root_when_original_missing(): void
    {
        $school = $this->makeSchool();
        $user = $this->makeUserWithPermissions(['file-manager.view', 'file-manager.manage'], $school);
        $this->bindActiveSchool($school);
        $provider = $this->makeLocalProvider($school);

        $folders = app(FolderService::class);
        $folder = $folders->create([
            'name' => 'Gone',
            'ownership' => 'school',
            'capabilities' => ['browse', 'upload', 'download', 'delete', 'create_folder', 'rename', 'move', 'attach'],
        ], $school->id);

        $file = app(FileService::class)->upload(
            UploadedFile::fake()->create('http-r.txt', 5, 'text/plain'),
            [
                'storage_provider_id' => $provider->id,
                'ownership' => 'school',
                'folder_id' => $folder->id,
            ],
            $school->id
        );

        app(FileLifecycleService::class)->trash($file, $school->id);
        $folder->delete();

        $trashedId = $file->id;

        $this->actingAs($user)
            ->postJson(route('file-manager.files.restore', $trashedId), [])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Original folder is missing or inaccessible. Provide an authorized alternative destination folder_id.']);

        $this->actingAs($user)
            ->postJson(route('file-manager.files.restore', $trashedId), ['folder_id' => null])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('file-manager.files.restore', $trashedId), ['folder_id' => ''])
            ->assertStatus(422);

        $this->assertTrue(File::query()->find($trashedId)->isTrashed());
    }

    public function test_restore_http_explicit_authorized_folder_succeeds_and_cross_school_rejected(): void
    {
        $schoolA = $this->makeSchool('School A');
        $schoolB = $this->makeSchool('School B');
        $user = $this->makeUserWithPermissions(['file-manager.view', 'file-manager.manage'], $schoolA);
        $this->bindActiveSchool($schoolA);

        $provider = $this->makeLocalProvider($schoolA);
        $folders = app(FolderService::class);

        $original = $folders->create([
            'name' => 'Orig',
            'ownership' => 'school',
            'capabilities' => ['browse', 'upload', 'download', 'delete', 'create_folder', 'rename', 'move', 'attach'],
        ], $schoolA->id);

        $alt = $folders->create([
            'name' => 'Alt',
            'ownership' => 'school',
            'capabilities' => ['browse', 'upload', 'download', 'delete', 'create_folder', 'rename', 'move', 'attach'],
        ], $schoolA->id);

        $foreign = $folders->create([
            'name' => 'Foreign',
            'ownership' => 'school',
            'capabilities' => ['browse', 'upload', 'download', 'delete', 'create_folder', 'rename', 'move', 'attach'],
        ], $schoolB->id);

        $file = app(FileService::class)->upload(
            UploadedFile::fake()->create('http-ok.txt', 5, 'text/plain'),
            [
                'storage_provider_id' => $provider->id,
                'ownership' => 'school',
                'folder_id' => $original->id,
            ],
            $schoolA->id
        );

        app(FileLifecycleService::class)->trash($file, $schoolA->id);
        $original->delete();

        $this->actingAs($user)
            ->postJson(route('file-manager.files.restore', $file->id), ['folder_id' => $foreign->id])
            ->assertStatus(422);

        $response = $this->actingAs($user)
            ->postJson(route('file-manager.files.restore', $file->id), ['folder_id' => $alt->id])
            ->assertOk();

        $this->assertFalse(File::query()->find($file->id)->isTrashed());
        $this->assertSame($alt->id, File::query()->find($file->id)->folder_id);
        $response->assertJsonPath('data.folder_id', $alt->id);
    }

    public function test_clear_purge_failure_http_requires_manage_and_respects_school_scope(): void
    {
        $schoolA = $this->makeSchool('School A');
        $schoolB = $this->makeSchool('School B');
        $manager = $this->makeUserWithPermissions(['file-manager.view', 'file-manager.manage'], $schoolA);
        $viewer = $this->makeUserWithPermissions(['file-manager.view'], $schoolA);
        $this->bindActiveSchool($schoolA);

        $provider = $this->makeLocalProvider($schoolA);
        $file = app(FileService::class)->upload(
            UploadedFile::fake()->create('fail.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $schoolA->id
        );
        $lifecycle = app(FileLifecycleService::class);
        $trashed = $lifecycle->trash($file, $schoolA->id);
        $trashed->purge_state = File::PURGE_STATE_FAILED;
        $trashed->save();

        $this->actingAs($viewer)
            ->postJson(route('file-manager.files.clear-purge-failure', $file->id))
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson(route('file-manager.files.clear-purge-failure', $file->id))
            ->assertOk()
            ->assertJsonPath('data.purge_state', null);

        $this->assertNull(File::query()->find($file->id)->purge_state);

        // Cross-school: bind B and try to clear A's file
        $providerB = $this->makeLocalProvider($schoolB, 'Local B');
        $fileB = app(FileService::class)->upload(
            UploadedFile::fake()->create('b.txt', 5, 'text/plain'),
            ['storage_provider_id' => $providerB->id, 'ownership' => 'school'],
            $schoolB->id
        );
        $tb = $lifecycle->trash($fileB, $schoolB->id);
        $tb->purge_state = File::PURGE_STATE_FAILED;
        $tb->save();

        $this->bindActiveSchool($schoolA);
        $this->actingAs($manager)
            ->postJson(route('file-manager.files.clear-purge-failure', $fileB->id))
            ->assertNotFound();
    }

    private function makeSchool(string $name = 'HTTP School'): School
    {
        return School::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function makeUserWithPermissions(array $permissions, School $school): User
    {
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'user_'.Str::random(8),
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['display_name' => $name]
            );
            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'user_type' => User::class,
                'school_id' => $school->id,
            ]);
            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'user_type' => User::class,
                'school_id' => null,
            ]);
        }

        DB::table('school_users')->insertOrIgnore([
            'school_id' => $school->id,
            'user_id' => $user->id,
        ]);

        return $user;
    }

    private function bindActiveSchool(?School $school): void
    {
        $manager = new class($school)
        {
            public function __construct(private $school) {}

            public function getActiveSchool(?Request $request = null): ?School
            {
                return $this->school;
            }

            public function setActiveSchool($school): void
            {
                $this->school = $school;
            }
        };

        $this->app->instance('schoolManager', $manager);

        if ($school !== null) {
            session(['active_school_id' => $school->id]);
        } else {
            session()->forget('active_school_id');
        }
    }

    private function makeLocalProvider(School $school, string $name = 'Local'): StorageProvider
    {
        $provider = new StorageProvider([
            'name' => $name,
            'driver' => StorageProviderDriver::Local,
            'ownership' => StorageProviderOwnership::School,
            'school_id' => $school->id,
            'config' => ['root' => $this->localRoot],
            'enabled' => true,
        ]);
        $provider->save();

        return $provider;
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
