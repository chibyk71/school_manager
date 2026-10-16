<?php

namespace Tests\Feature\Storage;

use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use App\Models\School;
use App\Models\StorageProvider;
use App\Models\User;
use App\Services\Storage\StorageProviderConnectionTester;
use App\Services\Storage\StorageProviderDependencyChecker;
use App\Services\Storage\StorageProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * File Manager Phase 1 — storage provider domain, secrets, isolation, connection test.
 */
class StorageProviderTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchool(string $name = 'Test Academy'): School
    {
        return School::query()->create([
            'name' => $name,
            'code' => strtoupper(substr(md5($name), 0, 8)),
            'slug' => str($name)->slug().'-'.substr(md5($name), 0, 4),
            'email' => str($name)->slug().'@test.example',
            'type' => 'private',
        ]);
    }

    private function makeUserWithPermissions(array $permissions, ?School $school = null): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $name) {
            $permission = \App\Models\Permission::query()->firstOrCreate(
                ['name' => $name],
                ['display_name' => $name]
            );
            // Laratrust assignment — school_id as team when provided
            if (method_exists($user, 'givePermission')) {
                $user->givePermission($permission, $school);
            } else {
                DB::table('permission_user')->insert([
                    'permission_id' => $permission->id,
                    'user_id' => $user->id,
                    'user_type' => User::class,
                    'school_id' => $school?->id,
                ]);
            }
        }

        return $user->fresh();
    }

    public function test_system_provider_requires_null_school_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $p = new StorageProvider([
            'name' => 'Bad System',
            'driver' => StorageProviderDriver::Local,
            'ownership' => StorageProviderOwnership::System,
            'school_id' => '00000000-0000-0000-0000-000000000001',
            'config' => [],
        ]);
        $p->assertOwnershipInvariants();
    }

    public function test_school_provider_requires_school_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $p = new StorageProvider([
            'name' => 'Bad School',
            'driver' => StorageProviderDriver::Local,
            'ownership' => StorageProviderOwnership::School,
            'school_id' => null,
            'config' => [],
        ]);
        $p->assertOwnershipInvariants();
    }

    public function test_secrets_are_encrypted_at_rest_and_redacted_in_resource(): void
    {
        $school = $this->makeSchool();

        $manager = app(StorageProviderManager::class);
        $provider = $manager->create([
            'name' => 'S3 Primary',
            'driver' => 's3',
            'ownership' => 'school',
            'school_id' => $school->id,
            'config' => [
                'key' => 'AKIAEXAMPLE',
                'region' => 'us-east-1',
                'bucket' => 'example-bucket',
            ],
            'secrets' => [
                'secret' => 'super-secret-value-xyz',
            ],
        ]);

        $raw = DB::table('storage_providers')->where('id', $provider->id)->value('secrets');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('super-secret-value-xyz', (string) $raw);

        $decrypted = $provider->getDecryptedSecrets();
        $this->assertSame('super-secret-value-xyz', $decrypted['secret'] ?? null);

        $resource = (new \App\Http\Resources\StorageProviderResource($provider))->resolve();
        $this->assertArrayNotHasKey('secrets', $resource);
        $this->assertTrue($resource['secrets_configured']);
        $this->assertSame('AKIAEXAMPLE', $resource['config']['key'] ?? null);
        $json = json_encode($resource);
        $this->assertStringNotContainsString('super-secret-value-xyz', (string) $json);
    }

    public function test_blank_secret_replacement_preserves_existing(): void
    {
        $school = $this->makeSchool('Preserve School');
        $manager = app(StorageProviderManager::class);

        $provider = $manager->create([
            'name' => 'S3 Keep',
            'driver' => 's3',
            'ownership' => 'school',
            'school_id' => $school->id,
            'config' => ['bucket' => 'b', 'region' => 'eu-west-1', 'key' => 'k'],
            'secrets' => ['secret' => 'original-secret'],
        ]);

        $manager->update($provider, [
            'name' => 'S3 Keep Updated',
            'secrets' => ['secret' => ''],
        ]);

        $provider->refresh();
        $this->assertSame('original-secret', $provider->getDecryptedSecrets()['secret'] ?? null);
        $this->assertSame('S3 Keep Updated', $provider->name);
    }

    public function test_supplied_secret_replacement_updates_stored_secret(): void
    {
        $school = $this->makeSchool('Replace School');
        $manager = app(StorageProviderManager::class);

        $provider = $manager->create([
            'name' => 'S3 Replace',
            'driver' => 's3',
            'ownership' => 'school',
            'school_id' => $school->id,
            'config' => ['bucket' => 'b', 'region' => 'eu-west-1', 'key' => 'k'],
            'secrets' => ['secret' => 'old-secret'],
        ]);

        $manager->update($provider, [
            'secrets' => ['secret' => 'new-secret'],
        ]);

        $provider->refresh();
        $this->assertSame('new-secret', $provider->getDecryptedSecrets()['secret'] ?? null);
    }

    public function test_clear_secrets_explicitly_removes_secrets(): void
    {
        $school = $this->makeSchool('Clear School');
        $manager = app(StorageProviderManager::class);

        $provider = $manager->create([
            'name' => 'S3 Clear',
            'driver' => 's3',
            'ownership' => 'school',
            'school_id' => $school->id,
            'config' => ['bucket' => 'b', 'region' => 'eu-west-1', 'key' => 'k'],
            'secrets' => ['secret' => 'to-clear'],
        ]);

        $manager->update($provider, ['clear_secrets' => true]);
        $provider->refresh();
        $this->assertFalse($provider->hasSecretsConfigured());
    }

    public function test_local_connection_test_does_not_persist(): void
    {
        $before = StorageProvider::query()->count();

        $tester = app(StorageProviderConnectionTester::class);
        $result = $tester->test([
            'driver' => 'local',
            'ownership' => 'system',
            'config' => ['root' => storage_path('app/private')],
        ]);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame($before, StorageProvider::query()->count());
    }

    public function test_dependency_check_is_honest_when_tracking_absent(): void
    {
        $school = $this->makeSchool('Deps School');
        $manager = app(StorageProviderManager::class);
        $provider = $manager->create([
            'name' => 'Local Deps',
            'driver' => 'local',
            'ownership' => 'school',
            'school_id' => $school->id,
            'config' => [],
        ]);

        $check = app(StorageProviderDependencyChecker::class)->check($provider);

        $this->assertFalse($check['tracking_implemented']);
        $this->assertSame([], $check['known_dependencies']);
        $this->assertNull($check['safe_to_remove']);
        $this->assertStringContainsString('not implemented', $check['summary']);
    }

    public function test_school_isolation_on_list_scope(): void
    {
        $schoolA = $this->makeSchool('School A');
        $schoolB = $this->makeSchool('School B');
        $manager = app(StorageProviderManager::class);

        $manager->create([
            'name' => 'A Local',
            'driver' => 'local',
            'ownership' => 'school',
            'school_id' => $schoolA->id,
            'config' => [],
        ]);
        $manager->create([
            'name' => 'B Local',
            'driver' => 'local',
            'ownership' => 'school',
            'school_id' => $schoolB->id,
            'config' => [],
        ]);
        $manager->create([
            'name' => 'System Local',
            'driver' => 'local',
            'ownership' => 'system',
            'config' => [],
        ]);

        $visibleA = $manager->listForContext($schoolA->id);
        $namesA = $visibleA->pluck('name')->all();

        $this->assertContains('A Local', $namesA);
        $this->assertContains('System Local', $namesA);
        $this->assertNotContains('B Local', $namesA);

        $foundB = $manager->findForContext(
            $visibleA->firstWhere('name', 'A Local')->id,
            $schoolB->id
        );
        // School B cannot resolve School A's provider by id
        $this->assertNull(
            $manager->findForContext(
                StorageProvider::query()->where('name', 'B Local')->value('id'),
                $schoolA->id
            )
        );
    }

    public function test_file_manager_settings_registered(): void
    {
        $registry = app(\App\Support\Settings\SettingsRegistry::class);

        $this->assertNotNull($registry->find('file_manager.default_provider_preference'));
        $this->assertNotNull($registry->find('file_manager.allow_school_provider_management'));

        $def = $registry->get('file_manager.default_provider_preference');
        $this->assertSame('school', $def->intendedScope);
        $this->assertSame('local', $def->default);
    }

    public function test_legacy_storage_settings_route_still_registered(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('settings.advanced.storage'),
            'Legacy Advanced Storage route must remain registered'
        );
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('settings.storage-providers.index'),
            'New storage providers route must be registered'
        );
    }
}
