<?php

namespace Tests\Feature\FileManager;

use App\Enums\FileManager\Capability;
use App\Enums\FileManager\Ownership;
use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use App\Models\FileManager\File;
use App\Models\School;
use App\Models\StorageProvider;
use App\Services\FileManager\FileService;
use App\Services\FileManager\FolderService;
use App\Support\FileManager\ModuleDefinitionRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Support\FileManagerTestSchema;
use Tests\TestCase;

/**
 * File Manager Phase 4 — browser search, rename, move, scoped listing.
 */
class FileManagerPhase4BrowserTest extends TestCase
{
    private string $localRoot;

    protected function setUp(): void
    {
        parent::setUp();
        FileManagerTestSchema::migrate();
        ModuleDefinitionRegistry::flush();

        $this->localRoot = storage_path('app/testing/fm-p4-'.Str::uuid()->toString());
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

    public function test_list_for_context_search_scopes_to_folder_and_ownership(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);

        $folders = app(FolderService::class);
        $files = app(FileService::class);

        $parent = $folders->create([
            'name' => 'Reports',
            'ownership' => Ownership::School->value,
            'school_id' => $school->id,
        ], $school->id);

        $folders->create([
            'name' => 'Alpha folder',
            'ownership' => Ownership::School->value,
            'school_id' => $school->id,
            'parent_id' => $parent->id,
        ], $school->id);
        $folders->create([
            'name' => 'Beta folder',
            'ownership' => Ownership::School->value,
            'school_id' => $school->id,
            'parent_id' => $parent->id,
        ], $school->id);

        $this->uploadNamed($files, $provider, $school->id, $parent->id, 'alpha-report.pdf');
        $this->uploadNamed($files, $provider, $school->id, $parent->id, 'beta-notes.txt');

        $matchedFolders = $folders->listForContext($school->id, $parent->id, 'Alpha');
        $this->assertCount(1, $matchedFolders);
        $this->assertSame('Alpha folder', $matchedFolders->first()->name);

        $matchedFiles = $files->listForContext($school->id, $parent->id, 'alpha');
        $this->assertCount(1, $matchedFiles);
        $this->assertSame('alpha-report.pdf', $matchedFiles->first()->original_filename);

        $other = $this->makeSchool('Other');
        $cross = $files->listForContext($other->id, null, 'alpha');
        $this->assertCount(0, $cross);
    }

    public function test_folder_rename_via_update_and_rejects_without_rename_capability(): void
    {
        $school = $this->makeSchool();
        $folders = app(FolderService::class);

        $folder = $folders->create([
            'name' => 'Old Name',
            'ownership' => Ownership::School->value,
            'school_id' => $school->id,
        ], $school->id);

        $updated = $folders->update($folder, ['name' => 'New Name'], $school->id);
        $this->assertSame('New Name', $updated->name);

        $folder->capabilities = [Capability::Browse->value];
        $folder->save();

        $this->expectException(InvalidArgumentException::class);
        $folders->update($folder->fresh(), ['name' => 'Nope'], $school->id);
    }

    public function test_file_move_respects_ownership_and_module_managed_restriction(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $folders = app(FolderService::class);
        $files = app(FileService::class);

        $dest = $folders->create([
            'name' => 'Dest',
            'ownership' => Ownership::School->value,
            'school_id' => $school->id,
        ], $school->id);

        $file = $this->uploadNamed($files, $provider, $school->id, null, 'movable.pdf');
        $moved = $files->move($file, $dest->id, $school->id);
        $this->assertSame($dest->id, $moved->folder_id);

        $moduleFile = $files->registerModuleManaged(
            UploadedFile::fake()->create('mod.pdf', 10, 'application/pdf'),
            ['storage_provider_id' => $provider->id],
            $school->id
        );
        $this->assertTrue($moduleFile->isModuleManaged());

        $this->expectException(InvalidArgumentException::class);
        $files->delete($moduleFile, $school->id);
    }

    public function test_search_term_is_bounded(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = app(FileService::class);

        $this->uploadNamed($files, $provider, $school->id, null, 'unique-needle-phase4.pdf');

        // Term longer than 100 chars is truncated; prefix-only noise must not match the file.
        $noisePrefix = str_repeat('z', 100);
        $longMiss = $noisePrefix.'unique-needle-phase4';
        $miss = $files->listForContext($school->id, null, $longMiss);
        $this->assertCount(0, $miss, 'Search term must be truncated to 100 chars so trailing needle is ignored');

        // A short, relevant term still finds the file.
        $hit = $files->listForContext($school->id, null, 'unique-needle');
        $this->assertCount(1, $hit);
        $this->assertSame('unique-needle-phase4.pdf', $hit->first()->original_filename);

        // Explicit limit binds result size.
        $this->uploadNamed($files, $provider, $school->id, null, 'unique-needle-phase4-b.pdf');
        $limited = $files->listForContext($school->id, null, 'unique-needle', 1);
        $this->assertCount(1, $limited);
    }

    public function test_ordinary_browse_excludes_trashed(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $files = app(FileService::class);

        $file = $this->uploadNamed($files, $provider, $school->id, null, 'soon-trash.pdf');
        $files->delete($file, $school->id);

        $listed = $files->listForContext($school->id, null, 'soon-trash');
        $this->assertCount(0, $listed);
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

    private function uploadNamed(
        FileService $files,
        StorageProvider $provider,
        string $schoolId,
        ?string $folderId,
        string $name,
    ): File {
        $uploaded = UploadedFile::fake()->create($name, 20, 'application/pdf');

        return $files->upload($uploaded, [
            'school_id' => $schoolId,
            'folder_id' => $folderId,
            'storage_provider_id' => $provider->id,
        ], $schoolId);
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
