<?php

namespace Tests\Feature\FileManager;

use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use App\Models\FileManager\Attachment;
use App\Models\FileManager\File;
use App\Models\School;
use App\Models\StorageProvider;
use App\Services\FileManager\AttachmentService;
use App\Services\FileManager\FileLifecycleService;
use App\Services\FileManager\FileService;
use App\Services\FileManager\FolderService;
use App\Services\Storage\StorageProviderDependencyChecker;
use App\Services\Storage\StorageProviderManager;
use App\Support\FileManager\ModuleDefinitionRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\FileManagerTestSchema;
use Tests\TestCase;

/**
 * File Manager Phase 3 — trash, restore, purge, provider dependency integrity.
 */
class FileManagerPhase3LifecycleTest extends TestCase
{
    private string $localRoot;

    protected function setUp(): void
    {
        parent::setUp();
        FileManagerTestSchema::migrate();
        ModuleDefinitionRegistry::flush();

        $this->localRoot = storage_path('app/testing/fm-p3-'.Str::uuid()->toString());
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

    public function test_fm_managed_file_can_be_trashed_and_hidden_from_browse(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();

        $file = $files->upload(
            UploadedFile::fake()->create('doc.txt', 10, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $this->assertFalse($file->isTrashed());
        $this->assertCount(1, $files->listForContext($school->id, null));

        $lifecycle = $this->lifecycleService();
        $trashed = $lifecycle->trash($file, $school->id);

        $this->assertTrue($trashed->isTrashed());
        $this->assertNotNull($trashed->purge_eligible_at);
        $this->assertEquals(
            $trashed->trashed_at->copy()->addDays(30)->timestamp,
            $trashed->purge_eligible_at->timestamp
        );

        $disk = Storage::build(['driver' => 'local', 'root' => $this->localRoot, 'throw' => true]);
        $this->assertTrue($disk->exists($file->storage_key));

        $this->assertCount(0, $files->listForContext($school->id, null));
        $this->assertNull($files->findForContext($file->id, $school->id));
        $this->assertCount(1, $lifecycle->listTrashed($school->id));
    }

    public function test_module_managed_file_cannot_be_trashed(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();

        $file = $files->registerModuleManaged(
            UploadedFile::fake()->create('mod.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school', 'module_code' => 'students'],
            $school->id
        );

        $this->expectException(InvalidArgumentException::class);
        $this->lifecycleService()->trash($file, $school->id);
    }

    public function test_attachments_preserved_and_block_download_while_trashed(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('att.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        Attachment::query()->create([
            'id' => (string) Str::uuid(),
            'file_id' => $file->id,
            'owner_type' => School::class,
            'owner_id' => $school->id,
            'purpose' => 'default',
            'lifecycle' => 'module',
        ]);

        $trashed = $lifecycle->trash($file, $school->id);
        $this->assertTrue($trashed->attachments()->exists());

        $this->expectException(InvalidArgumentException::class);
        $files->retrieve($trashed, $school->id);
    }

    public function test_purge_blocked_while_attachments_remain_and_allowed_after(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));

        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('p.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $att = Attachment::query()->create([
            'id' => (string) Str::uuid(),
            'file_id' => $file->id,
            'owner_type' => School::class,
            'owner_id' => $school->id,
            'purpose' => 'default',
            'lifecycle' => 'module',
        ]);

        $trashed = $lifecycle->trash($file, $school->id);
        $trashed->purge_eligible_at = now()->subMinute();
        $trashed->save();

        $this->assertFalse($lifecycle->isEligibleForImmediatePurge($trashed->fresh()));

        $att->delete();
        $this->assertTrue($lifecycle->isEligibleForImmediatePurge($trashed->fresh()));

        $lifecycle->purge($trashed->fresh());
        $this->assertNull(File::query()->find($file->id));

        Carbon::setTestNow();
    }

    public function test_restore_to_original_folder_and_stale_purge_does_not_delete(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $folders = $this->folderService();
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $folder = $folders->create([
            'name' => 'Docs',
            'ownership' => 'school',
            'capabilities' => ['browse', 'upload', 'download', 'delete', 'create_folder', 'rename', 'move', 'attach'],
        ], $school->id);

        $file = $files->upload(
            UploadedFile::fake()->create('r.txt', 5, 'text/plain'),
            [
                'storage_provider_id' => $provider->id,
                'ownership' => 'school',
                'folder_id' => $folder->id,
            ],
            $school->id
        );

        $trashed = $lifecycle->trash($file, $school->id);
        $restored = $lifecycle->restore($trashed, $school->id);

        $this->assertFalse($restored->isTrashed());
        $this->assertSame($folder->id, $restored->folder_id);
        $this->assertNull($restored->purge_eligible_at);

        $lifecycle->purge($restored->fresh());
        $this->assertNotNull(File::query()->find($file->id));
    }

    public function test_restore_requires_alternative_when_original_folder_missing(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $folders = $this->folderService();
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $folder = $folders->create([
            'name' => 'Temp',
            'ownership' => 'school',
            'capabilities' => ['browse', 'upload', 'download', 'delete', 'create_folder', 'rename', 'move', 'attach'],
        ], $school->id);

        $file = $files->upload(
            UploadedFile::fake()->create('x.txt', 5, 'text/plain'),
            [
                'storage_provider_id' => $provider->id,
                'ownership' => 'school',
                'folder_id' => $folder->id,
            ],
            $school->id
        );

        $lifecycle->trash($file, $school->id);
        $folder->delete();

        $trashed = File::query()->find($file->id);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('alternative');
        $lifecycle->restore($trashed, $school->id, null);
    }

    public function test_provider_deletion_refused_while_dependencies_including_trash(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('dep.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );
        $lifecycle->trash($file, $school->id);

        $checker = app(StorageProviderDependencyChecker::class);
        $report = $checker->check($provider->fresh());
        $this->assertFalse($report['safe_to_remove']);
        $this->assertSame(1, $report['file_count']);

        $manager = app(StorageProviderManager::class);
        $this->expectException(InvalidArgumentException::class);
        $manager->delete($provider, $school->id);
    }

    public function test_disabled_provider_rejects_new_upload_but_retains_existing(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();

        $file = $files->upload(
            UploadedFile::fake()->create('keep.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $provider->enabled = false;
        $provider->save();

        $payload = $files->retrieve($file->fresh(), $school->id);
        $this->assertIsResource($payload['stream']);
        if (is_resource($payload['stream'])) {
            fclose($payload['stream']);
        }

        $this->expectException(InvalidArgumentException::class);
        $files->upload(
            UploadedFile::fake()->create('new.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );
    }

    public function test_repeated_trash_is_idempotent_and_does_not_reset_retention(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 10:00:00'));
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('idem.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $t1 = $lifecycle->trash($file, $school->id);
        $eligible = $t1->purge_eligible_at->copy();

        Carbon::setTestNow(Carbon::parse('2026-06-05 10:00:00'));
        $t2 = $lifecycle->trash($t1, $school->id);
        $this->assertEquals($eligible->timestamp, $t2->purge_eligible_at->timestamp);

        Carbon::setTestNow();
    }


    public function test_purge_fails_closed_when_storage_key_unresolvable(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('nokey.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $trashed = $lifecycle->trash($file, $school->id);
        $trashed->purge_eligible_at = now()->subMinute();
        $trashed->storage_key = '';
        $trashed->save();

        try {
            $lifecycle->purge($trashed->fresh());
            $this->fail('Expected RuntimeException for unresolvable storage key');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('storage key', strtolower($e->getMessage()));
        }

        $this->assertNotNull(File::query()->find($file->id), 'File row must remain when purge fails closed');
    }

    public function test_purge_fails_closed_when_provider_unresolvable(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('noprov.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $trashed = $lifecycle->trash($file, $school->id);
        $trashed->purge_eligible_at = now()->subMinute();
        // Point at a non-existent provider id without cascade issues.
        $missingId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('fm_files')
            ->where('id', $trashed->id)
            ->update(['storage_provider_id' => $missingId]);

        try {
            $lifecycle->purge(File::query()->find($file->id));
            $this->fail('Expected RuntimeException for unresolvable provider');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('provider', strtolower($e->getMessage()));
        }

        $this->assertNotNull(File::query()->find($file->id));
    }

    public function test_attach_refuses_file_while_purge_state_deleting_physical(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();

        $file = $files->upload(
            UploadedFile::fake()->create('race.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        // Simulate claim without completing purge (active path would not trash first for attach test:
        // force purge_state on an active file to exercise the shared lock gate).
        $file->purge_state = File::PURGE_STATE_DELETING_PHYSICAL;
        $file->save();

        $attachments = app(AttachmentService::class);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('purged');
        $attachments->attach($file->fresh(), [
            'owner_type' => School::class,
            'owner_id' => $school->id,
        ], $school->id);
    }

    public function test_attach_refuses_trashed_file(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('tratt.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );
        $lifecycle->trash($file, $school->id);

        // attach uses findForContext (active-only) — should fail closed.
        $attachments = app(AttachmentService::class);
        $this->expectException(InvalidArgumentException::class);
        $attachments->attach($file->fresh(), [
            'owner_type' => School::class,
            'owner_id' => $school->id,
        ], $school->id);
    }

    public function test_upload_registration_fails_when_provider_disabled_under_lock(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();

        // Disable after resolve would be concurrent; here disable before upload.
        $provider->enabled = false;
        $provider->save();

        $this->expectException(InvalidArgumentException::class);
        $files->upload(
            UploadedFile::fake()->create('dis.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );
    }

    public function test_restore_with_alternative_folder_when_original_missing(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $folders = $this->folderService();
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $original = $folders->create([
            'name' => 'Original',
            'ownership' => 'school',
            'capabilities' => ['browse', 'upload', 'download', 'delete', 'create_folder', 'rename', 'move', 'attach'],
        ], $school->id);

        $alt = $folders->create([
            'name' => 'Alt',
            'ownership' => 'school',
            'capabilities' => ['browse', 'upload', 'download', 'delete', 'create_folder', 'rename', 'move', 'attach'],
        ], $school->id);

        $file = $files->upload(
            UploadedFile::fake()->create('alt.txt', 5, 'text/plain'),
            [
                'storage_provider_id' => $provider->id,
                'ownership' => 'school',
                'folder_id' => $original->id,
            ],
            $school->id
        );

        $lifecycle->trash($file, $school->id);
        $original->delete();

        $restored = $lifecycle->restore(File::query()->find($file->id), $school->id, $alt->id);
        $this->assertFalse($restored->isTrashed());
        $this->assertSame($alt->id, $restored->folder_id);
    }

    private function fileService(): FileService
    {
        return app(FileService::class);
    }

    private function lifecycleService(): FileLifecycleService
    {
        return app(FileLifecycleService::class);
    }

    private function folderService(): FolderService
    {
        return app(FolderService::class);
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
