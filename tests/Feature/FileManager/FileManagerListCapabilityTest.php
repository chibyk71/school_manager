<?php

namespace Tests\Feature\FileManager;

use App\Enums\FileManager\Capability;
use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use App\Models\School;
use App\Models\StorageProvider;
use App\Services\FileManager\FileService;
use App\Services\FileManager\FolderService;
use App\Support\FileManager\FolderCapabilityResolver;
use App\Support\FileManager\ModuleDefinitionRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Support\FileManagerTestSchema;
use Tests\TestCase;

/**
 * Regression: file listing must honor folder Browse capability and ownership context.
 */
class FileManagerListCapabilityTest extends TestCase
{
    private string $localRoot;

    protected function setUp(): void
    {
        parent::setUp();
        FileManagerTestSchema::migrate();
        ModuleDefinitionRegistry::flush();

        $this->localRoot = storage_path('app/testing/fm-list-'.Str::uuid()->toString());
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

    public function test_list_files_in_folder_requires_browse_capability(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $folder = $this->folderService()->create([
            'name' => 'Browsable',
            'module_code' => 'general',
            'capabilities' => [
                Capability::Browse->value,
                Capability::Upload->value,
                Capability::Download->value,
            ],
            'default_storage_provider_id' => $provider->id,
        ], $school->id);
        $this->fileService()->upload(
            UploadedFile::fake()->create('listed.txt', 10),
            ['folder_id' => $folder->id],
            $school->id
        );

        $items = $this->fileService()->listForContext($school->id, $folder->id);
        $this->assertCount(1, $items);
        $this->assertSame('listed.txt', $items->first()->original_filename);
    }

    public function test_list_files_rejects_folder_without_browse_capability(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $folder = $this->folderService()->create([
            'name' => 'No browse',
            'module_code' => 'student',
            'capabilities' => [
                Capability::Upload->value,
                Capability::Download->value,
            ],
            'default_storage_provider_id' => $provider->id,
        ], $school->id);
        $this->fileService()->upload(
            UploadedFile::fake()->create('hidden.txt', 10),
            ['folder_id' => $folder->id],
            $school->id
        );

        $this->expectException(InvalidArgumentException::class);
        $this->fileService()->listForContext($school->id, $folder->id);
    }

    public function test_list_files_rejects_cross_school_folder_id(): void
    {
        $schoolA = $this->makeSchool('A');
        $schoolB = $this->makeSchool('B');
        $providerA = $this->makeLocalProvider($schoolA);
        $folderA = $this->folderService()->create([
            'name' => 'A root',
            'default_storage_provider_id' => $providerA->id,
        ], $schoolA->id);
        $this->fileService()->upload(
            UploadedFile::fake()->create('a.txt', 10),
            ['folder_id' => $folderA->id],
            $schoolA->id
        );

        $this->expectException(InvalidArgumentException::class);
        $this->fileService()->listForContext($schoolB->id, $folderA->id);
    }

    public function test_standalone_file_listing_unaffected_by_folder_capabilities(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        $this->fileService()->upload(
            UploadedFile::fake()->create('root.txt', 10),
            ['storage_provider_id' => $provider->id],
            $school->id
        );

        $items = $this->fileService()->listForContext($school->id, null);
        $this->assertCount(1, $items);
        $this->assertSame('root.txt', $items->first()->original_filename);
    }

    /**
     * Stored capabilities that exceed the current module ceiling must be
     * intersected at read time — the registry is authoritative even if
     * write-time clamping was skipped or the ceiling later tightened.
     */
    public function test_effective_capabilities_reapply_module_ceiling_to_stored_list(): void
    {
        $school = $this->makeSchool();
        $provider = $this->makeLocalProvider($school);
        // student module max does not include Move.
        $folder = $this->folderService()->create([
            'name' => 'Student docs',
            'module_code' => 'student',
            'capabilities' => [
                Capability::Browse->value,
                Capability::Upload->value,
                Capability::Download->value,
            ],
            'default_storage_provider_id' => $provider->id,
        ], $school->id);

        // Simulate inconsistent / pre-ceiling-change stored data (bypass write clamp).
        $folder->capabilities = [
            Capability::Browse->value,
            Capability::Upload->value,
            Capability::Download->value,
            Capability::Move->value, // not in student max
        ];
        $folder->save();
        $folder->refresh();

        $effective = FolderCapabilityResolver::effective($folder);
        $this->assertContains(Capability::Browse->value, $effective);
        $this->assertContains(Capability::Upload->value, $effective);
        $this->assertContains(Capability::Download->value, $effective);
        $this->assertNotContains(Capability::Move->value, $effective);
        $this->assertFalse(FolderCapabilityResolver::allows($folder, Capability::Move));
        $this->assertTrue(FolderCapabilityResolver::allows($folder, Capability::Browse));
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
