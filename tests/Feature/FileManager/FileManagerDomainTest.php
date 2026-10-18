<?php

namespace Tests\Feature\FileManager;

use App\Enums\FileManager\Capability;
use App\Enums\FileManager\ManagedBy;
use App\Enums\FileManager\Ownership;
use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use App\Models\FileManager\Attachment;
use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Models\FileManager\SchoolModuleConfiguration;
use App\Models\School;
use App\Models\StorageProvider;
use App\Models\User;
use App\Services\FileManager\AttachmentService;
use App\Services\FileManager\FileObjectStorage;
use App\Services\FileManager\FileService;
use App\Services\FileManager\FolderService;
use App\Services\FileManager\ModuleConfigurationService;
use App\Services\Storage\StorageProviderConnectionTester;
use App\Services\Storage\StorageProviderManager;
use App\Support\FileManager\AttachableOwnerRegistry;
use App\Support\FileManager\ModuleDefinitionRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\FileManagerTestSchema;
use Tests\TestCase;

/**
 * File Manager Phase 2 — ownership, hierarchy, upload consistency, attachments, modules.
 */
class FileManagerDomainTest extends TestCase
{
    private string $localRoot;

    protected function setUp(): void
    {
        parent::setUp();
        FileManagerTestSchema::migrate();
        ModuleDefinitionRegistry::flush();

        $this->localRoot = storage_path('app/testing/fm-'.Str::uuid()->toString());
        if (! is_dir($this->localRoot)) {
            mkdir($this->localRoot, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->localRoot)) {
            $this->deleteTree($this->localRoot);
        }
        parent::tearDown();
    }

    public function test_school_folder_requires_school_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $folder = new Folder([
            'ownership' => Ownership::School,
            'school_id' => null,
            'name' => 'Bad',
        ]);
        $folder->save();
    }

    public function test_system_folder_forbids_school_id(): void
    {
        $school = $this->makeSchool();
        $this->expectException(InvalidArgumentException::class);
        $folder = new Folder([
            'ownership' => Ownership::System,
            'school_id' => $school->id,
            'name' => 'Bad',
        ]);
        $folder->save();
    }

    public function test_nested_folders_and_cycle_rejection(): void
    {
        $school = $this->makeSchool();
        $svc = $this->folderService();

        $root = $svc->create(['name' => 'Root'], $school->id);
        $child = $svc->create(['name' => 'Child', 'parent_id' => $root->id], $school->id);
        $grand = $svc->create(['name' => 'Grand', 'parent_id' => $child->id], $school->id);

        $this->assertSame($root->id, $child->parent_id);
        $this->assertSame($child->id, $grand->parent_id);

        // Identity stable on rename
        $renamed = $svc->update($child, ['name' => 'Child Renamed'], $school->id);
        $this->assertSame($child->id, $renamed->id);
        $this->assertSame('Child Renamed', $renamed->name);

        // Cycle: move root under grand
        $this->expectException(InvalidArgumentException::class);
        $svc->move($root, $grand->id, $school->id);
    }

    public function test_self_parent_rejected(): void
    {
        $school = $this->makeSchool();
        $svc = $this->folderService();
        $root = $svc->create(['name' => 'Root'], $school->id);

        $this->expectException(InvalidArgumentException::class);
        $svc->move($root, $root->id, $school->id);
    }

    public function test_cross_school_parent_rejected(): void
    {
        $a = $this->makeSchool('A');
        $b = $this->makeSchool('B');
        $svc = $this->folderService();
        $rootA = $svc->create(['name' => 'A Root'], $a->id);

        $this->expectException(InvalidArgumentException::class);
        $svc->create(['name' => 'B Child', 'parent_id' => $rootA->id], $b->id);
    }

    public function test_non_empty_folder_cannot_be_deleted(): void
    {
        $school = $this->makeSchool();
        $svc = $this->folderService();
        $root = $svc->create(['name' => 'Root'], $school->id);
        $svc->create(['name' => 'Child', 'parent_id' => $root->id], $school->id);

        $this->expectException(InvalidArgumentException::class);
        $svc->delete($root, $school->id);
    }

    public function test_module_capability_ceiling_enforced(): void
    {
        $school = $this->makeSchool();
        $svc = $this->folderService();

        $this->expectException(InvalidArgumentException::class);
        $svc->create([
            'name' => 'Student docs',
            'module_code' => 'student',
            'capabilities' => [Capability::CreateFolder->value], // not in student max
        ], $school->id);
    }

    public function test_unknown_module_code_rejected(): void
    {
        $school = $this->makeSchool();
        $svc = $this->folderService();

        $this->expectException(InvalidArgumentException::class);
        $svc->create(['name' => 'X', 'module_code' => 'not-a-real-module'], $school->id);
    }

    public function test_disabled_module_blocks_new_folder(): void
    {
        $school = $this->makeSchool();
        $modules = app(ModuleConfigurationService::class);
        $modules->configure($school->id, 'general', ['enabled' => false]);

        $svc = $this->folderService();
        $this->expectException(InvalidArgumentException::class);
        $svc->create(['name' => 'X', 'module_code' => 'general'], $school->id);
    }

    public function test_module_disable_does_not_delete_existing_folder(): void
    {
        $school = $this->makeSchool();
        $svc = $this->folderService();
        $folder = $svc->create(['name' => 'Keep', 'module_code' => 'general'], $school->id);

        $modules = app(ModuleConfigurationService::class);
        $modules->configure($school->id, 'general', ['enabled' => false]);

        $this->assertDatabaseHas('fm_folders', ['id' => $folder->id, 'name' => 'Keep']);
    }

    public function test_reenable_preserves_settings(): void
    {
        $school = $this->makeSchool();
        $modules = app(ModuleConfigurationService::class);
        $modules->configure($school->id, 'general', [
            'enabled' => true,
            'settings' => ['note' => 'keep-me'],
        ]);
        $modules->configure($school->id, 'general', ['enabled' => false]);
        $config = $modules->configure($school->id, 'general', ['enabled' => true]);

        $this->assertTrue($config->enabled);
        $this->assertSame(['note' => 'keep-me'], $config->settings);
    }

    public function test_successful_upload_and_opaque_storage_key(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $fileSvc = $this->fileService();

        $upload = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
        $file = $fileSvc->upload($upload, [
            'storage_provider_id' => $provider->id,
        ], $school->id);

        $this->assertNotNull($file->id);
        $this->assertSame('report.pdf', $file->original_filename);
        $this->assertStringStartsWith('fm/', $file->storage_key);
        $this->assertStringNotContainsString('report', $file->storage_key);
        $this->assertTrue(is_file($this->localRoot.'/'.$file->storage_key) || Storage::build([
            'driver' => 'local',
            'root' => $this->localRoot,
        ])->exists($file->storage_key));
    }

    public function test_file_folder_ownership_mismatch_rejected(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $folder = $this->folderService()->create(['name' => 'Root'], $school->id);

        // Platform context cannot place into school folder
        $this->expectException(InvalidArgumentException::class);
        $this->fileService()->upload(
            UploadedFile::fake()->create('x.txt', 10),
            ['folder_id' => $folder->id, 'storage_provider_id' => $provider->id],
            null
        );
    }

    public function test_school_isolation_on_find(): void
    {
        $a = $this->makeSchool('A');
        $b = $this->makeSchool('B');
        $provider = $this->makeLocalProvider($a);
        $file = $this->fileService()->upload(
            UploadedFile::fake()->create('a.txt', 10),
            ['storage_provider_id' => $provider->id],
            $a->id
        );

        $this->assertNull($this->fileService()->findForContext($file->id, $b->id));
        $this->assertNotNull($this->fileService()->findForContext($file->id, $a->id));
    }

    public function test_ordinary_upload_ignores_client_managed_by_and_is_file_manager_managed(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $file = $this->fileService()->upload(
            UploadedFile::fake()->create('client.txt', 10),
            [
                'storage_provider_id' => $provider->id,
                'managed_by' => ManagedBy::Module->value, // must be ignored
            ],
            $school->id
        );

        $this->assertTrue($file->isManagedByFileManager());
        $this->assertFalse($file->isModuleManaged());
    }

    public function test_module_managed_file_cannot_be_deleted_via_file_manager(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $file = $this->fileService()->registerModuleManaged(
            UploadedFile::fake()->create('mod.txt', 10),
            ['storage_provider_id' => $provider->id],
            $school->id
        );

        $this->assertTrue($file->isModuleManaged());
        $this->expectException(InvalidArgumentException::class);
        $this->fileService()->delete($file, $school->id);
    }

    public function test_attachment_allow_list_and_detach_keeps_file(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $file = $this->fileService()->upload(
            UploadedFile::fake()->create('att.txt', 10),
            ['storage_provider_id' => $provider->id],
            $school->id
        );

        $attachments = app(AttachmentService::class);
        $attachment = $attachments->attach($file, [
            'owner_type' => 'school',
            'owner_id' => $school->id,
            'purpose' => 'archive',
        ], $school->id);

        $this->assertDatabaseHas('fm_attachments', ['id' => $attachment->id]);

        $this->expectException(InvalidArgumentException::class);
        $attachments->attach($file, [
            'owner_type' => 'App\\Models\\NotAllowListed',
            'owner_id' => $school->id,
        ], $school->id);
    }

    public function test_detach_leaves_file_intact(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $file = $this->fileService()->upload(
            UploadedFile::fake()->create('att2.txt', 10),
            ['storage_provider_id' => $provider->id],
            $school->id
        );
        $attachments = app(AttachmentService::class);
        $attachment = $attachments->attach($file, [
            'owner_type' => School::class,
            'owner_id' => $school->id,
        ], $school->id);

        $attachments->detach($attachment, $school->id);
        $this->assertDatabaseMissing('fm_attachments', ['id' => $attachment->id]);
        $this->assertDatabaseHas('fm_files', ['id' => $file->id]);
    }

    public function test_attachment_rejects_user_without_school_link(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Unlinked',
            'email' => 'unlinked@example.test',
            'password' => bcrypt('secret'),
        ]);
        $file = $this->fileService()->upload(
            UploadedFile::fake()->create('x.txt', 10),
            ['storage_provider_id' => $provider->id],
            $school->id
        );

        $this->expectException(InvalidArgumentException::class);
        app(AttachmentService::class)->attach($file, [
            'owner_type' => 'user',
            'owner_id' => $user->id,
        ], $school->id);
    }

    public function test_attachment_rejects_cross_school_owner(): void
    {
        $schoolA = $this->makeSchool('A');
        $schoolB = $this->makeSchool('B');
        $provider = $this->makeLocalProvider($schoolA);
        $file = $this->fileService()->upload(
            UploadedFile::fake()->create('x.txt', 10),
            ['storage_provider_id' => $provider->id],
            $schoolA->id
        );

        $this->expectException(InvalidArgumentException::class);
        app(AttachmentService::class)->attach($file, [
            'owner_type' => 'school',
            'owner_id' => $schoolB->id,
        ], $schoolA->id);
    }

    public function test_folder_capability_blocks_upload_when_not_allowed(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $folder = $this->folderService()->create([
            'name' => 'Read only',
            'module_code' => 'student',
            'capabilities' => [
                Capability::Browse->value,
                Capability::Download->value,
            ],
            'default_storage_provider_id' => $provider->id,
        ], $school->id);

        $this->expectException(InvalidArgumentException::class);
        $this->fileService()->upload(
            UploadedFile::fake()->create('blocked.txt', 10),
            ['folder_id' => $folder->id],
            $school->id
        );
    }

    public function test_folder_capability_blocks_delete_when_not_allowed(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $folder = $this->folderService()->create([
            'name' => 'No delete',
            'module_code' => 'general',
            'capabilities' => [
                Capability::Browse->value,
                Capability::Upload->value,
                Capability::Download->value,
            ],
            'default_storage_provider_id' => $provider->id,
        ], $school->id);
        $file = $this->fileService()->upload(
            UploadedFile::fake()->create('keep.txt', 10),
            ['folder_id' => $folder->id],
            $school->id
        );

        $this->expectException(InvalidArgumentException::class);
        $this->fileService()->delete($file, $school->id);
    }

    public function test_file_resource_hides_storage_key(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $file = $this->fileService()->upload(
            UploadedFile::fake()->create('sec.txt', 10),
            ['storage_provider_id' => $provider->id],
            $school->id
        );

        $array = $file->toArray();
        $this->assertArrayNotHasKey('storage_key', $array);
        $resource = (new \App\Http\Resources\FileManager\FileResource($file))->resolve();
        $this->assertArrayNotHasKey('storage_key', $resource);
        $this->assertArrayNotHasKey('secrets', $resource);
    }

    public function test_default_provider_change_does_not_mutate_existing_file_provider(): void
    {
        $school = $this->makeSchool();
        $p1 = $this->makeLocalProvider($school, 'P1');
        $p2 = $this->makeLocalProvider($school, 'P2');
        $folder = $this->folderService()->create([
            'name' => 'Root',
            'default_storage_provider_id' => $p1->id,
        ], $school->id);
        $file = $this->fileService()->upload(
            UploadedFile::fake()->create('x.txt', 5),
            ['folder_id' => $folder->id],
            $school->id
        );
        $this->assertSame($p1->id, $file->storage_provider_id);

        $this->folderService()->update($folder, [
            'default_storage_provider_id' => $p2->id,
        ], $school->id);

        $this->assertSame($p1->id, $file->fresh()->storage_provider_id);
    }

    public function test_storage_key_generation_is_opaque(): void
    {
        $storage = app(FileObjectStorage::class);
        $key = $storage->generateStorageKey('pdf');
        $this->assertMatchesRegularExpression('#^fm/[0-9a-f-]{36}\.pdf$#', $key);
    }

    public function test_attachable_registry_resolves_aliases(): void
    {
        $this->assertSame(User::class, AttachableOwnerRegistry::resolve('user'));
        $this->assertTrue(AttachableOwnerRegistry::isAllowedClass(User::class));
    }

    private function folderService(): FolderService
    {
        return app(FolderService::class);
    }

    private function fileService(): FileService
    {
        return app(FileService::class);
    }

    private function makeSchool(string $name = 'Test School'): School
    {
        return School::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'is_active' => true,
        ]);
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
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
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
