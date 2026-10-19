<?php

namespace Tests\Feature\FileManager;

use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use App\Models\FileManager\File;
use App\Models\School;
use App\Models\StorageProvider;
use App\Services\FileManager\FileLifecycleService;
use App\Services\FileManager\FileService;
use App\Support\FileManager\ModuleDefinitionRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Support\FileManagerTestSchema;
use Tests\TestCase;

/**
 * Restore safety after ambiguous purge outcomes (Phase 3 review).
 */
class FileManagerPhase3RestoreSafetyTest extends TestCase
{
    private string $localRoot;

    protected function setUp(): void
    {
        parent::setUp();
        FileManagerTestSchema::migrate();
        ModuleDefinitionRegistry::flush();

        $this->localRoot = storage_path('app/testing/fm-p3-rs-'.Str::uuid()->toString());
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

    public function test_restore_refused_when_physical_object_deleted_after_purge_failure(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('gone.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $trashed = $lifecycle->trash($file, $school->id);
        $trashed->purge_eligible_at = now()->subMinute();
        $trashed->purge_state = File::PURGE_STATE_DELETING_PHYSICAL;
        $trashed->save();

        // Simulate successful physical delete then exhausted DB cleanup → terminal failed.
        $disk = Storage::build(['driver' => 'local', 'root' => $this->localRoot, 'throw' => true]);
        $this->assertTrue($disk->exists($file->storage_key));
        $disk->delete($file->storage_key);
        $this->assertFalse($disk->exists($file->storage_key));

        $lifecycle->markPurgeFailed($trashed->id, 'simulated_db_cleanup_exhaustion');
        $failed = File::query()->find($file->id);
        $this->assertNotNull($failed);
        $this->assertSame(File::PURGE_STATE_FAILED, $failed->purge_state);

        try {
            $lifecycle->restore($failed, $school->id);
            $this->fail('Expected restore to be refused when physical object is absent');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('physical object is no longer present', $e->getMessage());
        }

        $this->assertNotNull(File::query()->find($file->id), 'File row must remain after refused restore');
        $this->assertTrue(File::query()->find($file->id)->isTrashed());
        $this->assertSame(File::PURGE_STATE_FAILED, File::query()->find($file->id)->purge_state);
    }

    public function test_restore_allowed_when_object_still_exists_after_purge_failure(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('still.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $trashed = $lifecycle->trash($file, $school->id);
        $trashed->purge_state = File::PURGE_STATE_FAILED;
        $trashed->save();

        $disk = Storage::build(['driver' => 'local', 'root' => $this->localRoot, 'throw' => true]);
        $this->assertTrue($disk->exists($file->storage_key));

        $restored = $lifecycle->restore($trashed->fresh(), $school->id);
        $this->assertFalse($restored->isTrashed());
        $this->assertNull($restored->purge_state);
        $this->assertTrue($disk->exists($file->storage_key));
    }

    public function test_restore_fails_closed_when_provider_cannot_be_resolved(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('noprov-r.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $trashed = $lifecycle->trash($file, $school->id);
        $missingId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('fm_files')
            ->where('id', $trashed->id)
            ->update(['storage_provider_id' => $missingId, 'purge_state' => File::PURGE_STATE_FAILED]);

        $row = File::query()->find($file->id);
        try {
            $lifecycle->restore($row, $school->id);
            $this->fail('Expected restore to fail closed when provider is unresolvable');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('storage provider cannot be resolved', $e->getMessage());
        }

        $this->assertNotNull(File::query()->find($file->id));
        $this->assertTrue(File::query()->find($file->id)->isTrashed());
    }

    public function test_restore_fails_closed_when_object_existence_cannot_be_verified(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('unver.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $trashed = $lifecycle->trash($file, $school->id);
        // Point local root at a path that cannot be used as a safe root (empty / missing).
        $provider->config = ['root' => ''];
        $provider->save();

        try {
            $lifecycle->restore($trashed->fresh(), $school->id);
            $this->fail('Expected restore to fail closed when existence cannot be verified');
        } catch (InvalidArgumentException $e) {
            $message = strtolower($e->getMessage());
            $this->assertTrue(
                str_contains($message, 'could not be verified')
                || str_contains($message, 'storage provider')
                || str_contains($message, 'physical object'),
                'Unexpected message: '.$e->getMessage()
            );
        }

        $this->assertNotNull(File::query()->find($file->id));
        $this->assertTrue(File::query()->find($file->id)->isTrashed());
    }

    public function test_ordinary_restore_still_works_when_object_present(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = $this->fileService();
        $lifecycle = $this->lifecycleService();

        $file = $files->upload(
            UploadedFile::fake()->create('ok.txt', 5, 'text/plain'),
            ['storage_provider_id' => $provider->id, 'ownership' => 'school'],
            $school->id
        );

        $trashed = $lifecycle->trash($file, $school->id);
        $restored = $lifecycle->restore($trashed, $school->id);
        $this->assertFalse($restored->isTrashed());
        $this->assertNull($restored->purge_state);
    }

    private function fileService(): FileService
    {
        return app(FileService::class);
    }

    private function lifecycleService(): FileLifecycleService
    {
        return app(FileLifecycleService::class);
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
