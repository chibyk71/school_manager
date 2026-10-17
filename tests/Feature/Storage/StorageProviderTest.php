<?php

namespace Tests\Feature\Storage;

use App\Enums\StorageProviderDriver;
use App\Enums\StorageProviderOwnership;
use App\Http\Controllers\Settings\Storage\StorageProviderController;
use App\Http\Resources\StorageProviderResource;
use App\Models\School;
use App\Models\StorageProvider;
use App\Services\Storage\StorageProviderConnectionTester;
use App\Services\Storage\StorageProviderDependencyChecker;
use App\Services\Storage\StorageProviderManager;
use App\Settings\Definitions\FileManagerSettings;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Support\StorageProviderTestSchema;
use Tests\TestCase;

/**
 * File Manager Phase 1 — domain, secrets, isolation, connection safety, settings.
 *
 * Uses focused schema (not RefreshDatabase): full SQLite migrate is blocked by
 * an unrelated devices migration (drop column ip / staff section index).
 */
class StorageProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        StorageProviderTestSchema::build();
    }

    protected function tearDown(): void
    {
        StorageProviderTestSchema::drop();
        Model::reguard();
        parent::tearDown();
    }

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

    public function test_system_provider_requires_null_school_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

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
        $this->expectException(InvalidArgumentException::class);

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
            'config' => [
                'key' => 'AKIAEXAMPLE',
                'region' => 'us-east-1',
                'bucket' => 'example-bucket',
            ],
            'secrets' => [
                'secret' => 'super-secret-value-xyz',
            ],
        ], $school->id);

        $raw = DB::table('storage_providers')->where('id', $provider->id)->value('secrets');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('super-secret-value-xyz', (string) $raw);

        $decrypted = $provider->getDecryptedSecrets();
        $this->assertSame('super-secret-value-xyz', $decrypted['secret'] ?? null);

        $resource = (new StorageProviderResource($provider))->resolve();
        $this->assertArrayNotHasKey('secrets', $resource);
        $this->assertTrue($resource['secrets_configured']);
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
            'config' => ['bucket' => 'b', 'region' => 'eu-west-1', 'key' => 'k'],
            'secrets' => ['secret' => 'original-secret'],
        ], $school->id);

        $manager->update($provider, [
            'name' => 'S3 Keep Updated',
            'secrets' => ['secret' => ''],
        ], $school->id);

        $provider->refresh();
        $this->assertSame('original-secret', $provider->getDecryptedSecrets()['secret'] ?? null);
    }

    public function test_supplied_secret_replacement_updates_stored_secret(): void
    {
        $school = $this->makeSchool('Replace School');
        $manager = app(StorageProviderManager::class);

        $provider = $manager->create([
            'name' => 'S3 Replace',
            'driver' => 's3',
            'ownership' => 'school',
            'config' => ['bucket' => 'b', 'region' => 'eu-west-1', 'key' => 'k'],
            'secrets' => ['secret' => 'old-secret'],
        ], $school->id);

        $manager->update($provider, [
            'secrets' => ['secret' => 'new-secret'],
        ], $school->id);

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
            'config' => ['bucket' => 'b', 'region' => 'eu-west-1', 'key' => 'k'],
            'secrets' => ['secret' => 'to-clear'],
        ], $school->id);

        $manager->update($provider, ['clear_secrets' => true], $school->id);
        $provider->refresh();
        $this->assertFalse($provider->hasSecretsConfigured());
    }

    public function test_school_context_cannot_create_system_provider(): void
    {
        $school = $this->makeSchool('No System Create');
        $manager = app(StorageProviderManager::class);

        $this->expectException(InvalidArgumentException::class);
        $manager->create([
            'name' => 'System From School',
            'driver' => 'local',
            'ownership' => 'system',
            'config' => [],
        ], $school->id);
    }

    public function test_platform_context_cannot_create_school_provider(): void
    {
        $manager = app(StorageProviderManager::class);

        try {
            $manager->create([
                'name' => 'School Without Context',
                'driver' => 'local',
                'ownership' => 'school',
                'config' => [],
            ], null);
            $this->fail('Expected rejection when creating school provider without school context');
        } catch (InvalidArgumentException|ValidationException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_school_context_list_excludes_system_and_other_schools(): void
    {
        $schoolA = $this->makeSchool('School A');
        $schoolB = $this->makeSchool('School B');
        $manager = app(StorageProviderManager::class);

        $manager->create([
            'name' => 'A Local',
            'driver' => 'local',
            'ownership' => 'school',
            'config' => [],
        ], $schoolA->id);

        $manager->create([
            'name' => 'B Local',
            'driver' => 'local',
            'ownership' => 'school',
            'config' => [],
        ], $schoolB->id);

        $manager->create([
            'name' => 'System Local',
            'driver' => 'local',
            'ownership' => 'system',
            'config' => [],
        ], null);

        $visibleA = $manager->listForContext($schoolA->id);
        $namesA = $visibleA->pluck('name')->all();

        $this->assertContains('A Local', $namesA);
        $this->assertNotContains('B Local', $namesA);
        $this->assertNotContains('System Local', $namesA);

        $systemOnly = $manager->listForContext(null);
        $this->assertContains('System Local', $systemOnly->pluck('name')->all());
        $this->assertNotContains('A Local', $systemOnly->pluck('name')->all());
    }

    public function test_find_for_context_hides_system_from_school_and_cross_school(): void
    {
        $schoolA = $this->makeSchool('Find A');
        $schoolB = $this->makeSchool('Find B');
        $manager = app(StorageProviderManager::class);

        $system = $manager->create([
            'name' => 'Sys',
            'driver' => 'local',
            'ownership' => 'system',
            'config' => [],
        ], null);

        $a = $manager->create([
            'name' => 'A',
            'driver' => 'local',
            'ownership' => 'school',
            'config' => [],
        ], $schoolA->id);

        $this->assertNull($manager->findForContext($system->id, $schoolA->id));
        $this->assertNotNull($manager->findForContext($system->id, null));
        $this->assertNull($manager->findForContext($a->id, $schoolB->id));
        $this->assertNotNull($manager->findForContext($a->id, $schoolA->id));
        $this->assertNull($manager->findForContext($a->id, null));
    }

    public function test_school_cannot_update_system_provider_via_manager(): void
    {
        $school = $this->makeSchool('Update Boundary');
        $manager = app(StorageProviderManager::class);

        $system = $manager->create([
            'name' => 'Sys Up',
            'driver' => 'local',
            'ownership' => 'system',
            'config' => [],
        ], null);

        $this->expectException(InvalidArgumentException::class);
        $manager->update($system, ['name' => 'Hacked'], $school->id);
    }

    public function test_local_connection_test_succeeds_under_storage_path(): void
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

    public function test_local_connection_test_rejects_path_outside_storage(): void
    {
        $tester = app(StorageProviderConnectionTester::class);

        $result = $tester->test([
            'driver' => 'local',
            'ownership' => 'system',
            'config' => ['root' => '/etc'],
        ]);

        $this->assertFalse($result['success']);
        // /etc exists on Unix (outside permitted area); on Windows it may not exist.
        $this->assertMatchesRegularExpression(
            '/permitted|outside|does not exist/i',
            $result['message']
        );
    }

    public function test_local_connection_test_does_not_create_outside_storage_path(): void
    {
        $tester = app(StorageProviderConnectionTester::class);
        $outside = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sm-storage-probe-'.bin2hex(random_bytes(8));

        $this->assertDirectoryDoesNotExist($outside);

        $result = $tester->test([
            'driver' => 'local',
            'ownership' => 'system',
            'config' => ['root' => $outside],
        ]);

        $this->assertFalse($result['success'], $result['message'] ?? '');
        $this->assertDirectoryDoesNotExist($outside);

        $threw = false;
        try {
            $tester->assertSafeLocalRoot($outside);
        } catch (InvalidArgumentException $e) {
            $threw = true;
            $this->assertMatchesRegularExpression('/does not exist|outside|permitted|resolved/i', $e->getMessage());
        }
        $this->assertTrue($threw, 'assertSafeLocalRoot must reject paths outside storage');
        $this->assertDirectoryDoesNotExist($outside);
    }

    public function test_local_connection_test_rejects_nonexistent_path_inside_storage(): void
    {
        $tester = app(StorageProviderConnectionTester::class);
        $inside = storage_path('app/private/sm-nonexistent-'.bin2hex(random_bytes(8)));

        $this->assertDirectoryDoesNotExist($inside);

        $result = $tester->test([
            'driver' => 'local',
            'ownership' => 'system',
            'config' => ['root' => $inside],
        ]);

        $this->assertFalse($result['success'], $result['message'] ?? '');
        $this->assertStringContainsString('does not exist', strtolower($result['message']));
        $this->assertDirectoryDoesNotExist($inside);

        $threw = false;
        try {
            $tester->assertSafeLocalRoot($inside);
        } catch (InvalidArgumentException $e) {
            $threw = true;
            $this->assertStringContainsString('does not exist', strtolower($e->getMessage()));
        }
        $this->assertTrue($threw);
        $this->assertDirectoryDoesNotExist($inside);
    }

    public function test_local_connection_test_rejects_traversal(): void
    {
        $tester = app(StorageProviderConnectionTester::class);

        $result = $tester->test([
            'driver' => 'local',
            'ownership' => 'system',
            'config' => ['root' => storage_path('app/private/../../..')],
        ]);

        $this->assertFalse($result['success']);
    }

    public function test_s3_endpoint_must_be_http_url(): void
    {
        $tester = app(StorageProviderConnectionTester::class);

        $result = $tester->test([
            'driver' => 's3',
            'ownership' => 'system',
            'config' => [
                'bucket' => 'b',
                'region' => 'us-east-1',
                'endpoint' => 'ftp://evil.example',
            ],
            'secrets' => ['secret' => 'x'],
        ]);

        $this->assertFalse($result['success']);
        $this->assertMatchesRegularExpression('/http|not permitted|invalid/i', $result['message']);
    }

    public function test_dependency_check_is_honest_when_tracking_absent(): void
    {
        $school = $this->makeSchool('Deps School');
        $manager = app(StorageProviderManager::class);
        $provider = $manager->create([
            'name' => 'Local Deps',
            'driver' => 'local',
            'ownership' => 'school',
            'config' => [],
        ], $school->id);

        $check = app(StorageProviderDependencyChecker::class)->check($provider);

        $this->assertFalse($check['tracking_implemented']);
        $this->assertSame([], $check['known_dependencies']);
        $this->assertNull($check['safe_to_remove']);
        $this->assertStringContainsString('not implemented', $check['summary']);
    }

    public function test_file_manager_settings_registered(): void
    {
        $registry = app(SettingsRegistry::class);

        $this->assertNotNull($registry->find('file_manager.default_provider_preference'));
        $this->assertNotNull($registry->find('file_manager.allow_school_provider_management'));

        $def = $registry->get('file_manager.default_provider_preference');
        $this->assertSame('school', $def->intendedScope);
        $this->assertSame('local', $def->default);
    }

    public function test_allow_school_provider_management_setting_defaults_true(): void
    {
        $registry = new SettingsRegistry;
        FileManagerSettings::register($registry);
        $registry->seal();
        $resolver = new SettingsResolver($registry);

        $school = $this->makeSchool('Settings School');
        $scope = SettingsScope::school($school->id);

        $this->assertTrue((bool) $resolver->get('file_manager.allow_school_provider_management', $scope));
    }

    public function test_legacy_storage_settings_route_still_registered(): void
    {
        $this->assertTrue(
            Route::has('settings.advanced.storage'),
            'Legacy Advanced Storage route must remain registered'
        );
        $this->assertTrue(
            Route::has('settings.storage-providers.index'),
            'New storage providers route must be registered'
        );
    }

    public function test_endpoint_policy_rejects_loopback_and_metadata(): void
    {
        $tester = app(StorageProviderConnectionTester::class);

        $prohibited = [
            'http://127.0.0.1:9000',
            'http://localhost/minio',
            'http://169.254.169.254/latest/meta-data/',
            'http://[::1]/',
            'http://10.0.0.5',
            'http://192.168.1.1',
            'http://172.16.0.1',
            'ftp://s3.amazonaws.com',
            'https://user:pass@s3.example.com',
        ];

        foreach ($prohibited as $url) {
            $this->assertFalse(
                $tester->endpointIsAllowed($url),
                "Expected prohibited endpoint to be rejected: {$url}"
            );
        }
    }

    public function test_endpoint_policy_allows_public_https_hosts(): void
    {
        $tester = app(StorageProviderConnectionTester::class);

        $url = 'https://s3.amazonaws.com';
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $resolved = @gethostbynamel($host);
        if ($resolved === false || $resolved === []) {
            $this->markTestSkipped('DNS unavailable for s3.amazonaws.com in this environment');
        }

        $this->assertTrue($tester->endpointIsAllowed($url));
    }

    public function test_endpoint_allowlist_permits_configured_private_host(): void
    {
        config(['filesystems.connection_test_allowed_hosts' => ['minio.internal.example']]);
        $tester = app(StorageProviderConnectionTester::class);

        // Hostname allowlist is a policy decision (no DNS required).
        $this->assertTrue($tester->endpointIsAllowed('https://minio.internal.example:9000'));

        // Connect-time pin still requires resolvable addresses (fail closed).
        $ips = $tester->resolveEndpointIps('minio.internal.example');
        if ($ips === []) {
            $this->assertNull($tester->pinAllowedEndpoint('https://minio.internal.example:9000'));
        } else {
            $pin = $tester->pinAllowedEndpoint('https://minio.internal.example:9000');
            $this->assertNotNull($pin);
            $this->assertStringContainsString('minio.internal.example:9000:', $pin['curl_resolve'][0]);
        }
    }

    public function test_prohibited_ip_helper(): void
    {
        $tester = app(StorageProviderConnectionTester::class);

        $this->assertTrue($tester->isProhibitedIp('127.0.0.1'));
        $this->assertTrue($tester->isProhibitedIp('10.1.2.3'));
        $this->assertTrue($tester->isProhibitedIp('192.168.0.1'));
        $this->assertTrue($tester->isProhibitedIp('169.254.169.254'));
        $this->assertTrue($tester->isProhibitedIp('::1'));
        $this->assertFalse($tester->isProhibitedIp('8.8.8.8'));
    }

    public function test_connection_failure_does_not_log_sensitive_values(): void
    {
        $tester = app(StorageProviderConnectionTester::class);
        $secret = 'super-secret-value-should-not-log';
        $endpoint = 'https://127.0.0.1:9000';

        $result = $tester->test([
            'driver' => 's3',
            'ownership' => 'system',
            'config' => [
                'bucket' => 'b',
                'region' => 'us-east-1',
                'endpoint' => $endpoint,
                'key' => 'AKIAEXAMPLE',
            ],
            'secrets' => ['secret' => $secret],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString($secret, $result['message']);
        $this->assertStringNotContainsString('127.0.0.1', $result['message']);
        $this->assertStringNotContainsString('AKIAEXAMPLE', $result['message']);
    }

    public function test_school_provider_management_fails_closed_on_settings_error(): void
    {
        $school = $this->makeSchool('Fail Closed');

        // SettingsResolver is final — force failure via empty sealed registry
        // (unknown key throws), then expect management denied.
        $empty = new SettingsRegistry;
        $empty->seal();
        $this->app->instance(SettingsRegistry::class, $empty);
        $this->app->instance(SettingsResolver::class, new SettingsResolver($empty));

        $controller = $this->app->make(StorageProviderController::class);
        $method = new \ReflectionMethod($controller, 'schoolProviderManagementAllowed');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, $school->id));
    }

    public function test_school_soft_delete_soft_deletes_school_owned_providers_only(): void
    {
        $school = $this->makeSchool('Delete Lifecycle');
        $manager = app(StorageProviderManager::class);

        $schoolProvider = $manager->create([
            'name' => 'School Disk Lifecycle',
            'driver' => 'local',
            'ownership' => 'school',
            'config' => [],
        ], $school->id);

        $systemProvider = $manager->create([
            'name' => 'System Disk Lifecycle',
            'driver' => 'local',
            'ownership' => 'system',
            'config' => [],
        ], null);

        $school->delete();
        $school->refresh();

        $this->assertSoftDeleted('storage_providers', ['id' => $schoolProvider->id]);
        $this->assertDatabaseHas('storage_providers', [
            'id' => $systemProvider->id,
            'deleted_at' => null,
            'ownership' => 'system',
            'school_id' => null,
        ]);
        // Provenance: provider deleted_at matches school deleted_at exactly.
        $provider = StorageProvider::withTrashed()->find($schoolProvider->id);
        $this->assertNotNull($school->deleted_at);
        $this->assertTrue(
            $provider->deleted_at->equalTo($school->deleted_at),
            'Provider deleted_at must match school deleted_at for restore provenance'
        );
        $this->assertSame('school', $provider->ownership->value);
        $this->assertSame($school->id, $provider->school_id);
    }

    public function test_school_restore_restores_providers_deleted_with_school(): void
    {
        $school = $this->makeSchool('Restore Lifecycle');
        $manager = app(StorageProviderManager::class);

        $withSchool = $manager->create([
            'name' => 'With School Delete',
            'driver' => 'local',
            'ownership' => 'school',
            'config' => [],
        ], $school->id);

        $school->delete();
        $this->assertSoftDeleted('storage_providers', ['id' => $withSchool->id]);

        $school->restore();

        $this->assertDatabaseHas('storage_providers', [
            'id' => $withSchool->id,
            'deleted_at' => null,
            'ownership' => 'school',
            'school_id' => $school->id,
        ]);
    }

    public function test_school_restore_does_not_restore_pretrashed_providers(): void
    {
        $school = $this->makeSchool('Pretrash Lifecycle');
        $manager = app(StorageProviderManager::class);

        $pretrashed = $manager->create([
            'name' => 'Already Trashed',
            'driver' => 'local',
            'ownership' => 'school',
            'config' => [],
        ], $school->id);

        $pretrashed->delete();
        $this->assertSoftDeleted('storage_providers', ['id' => $pretrashed->id]);

        // Distinct earlier timestamp so it cannot match school soft-delete provenance.
        DB::table('storage_providers')
            ->where('id', $pretrashed->id)
            ->update(['deleted_at' => now()->subMinutes(5)]);

        $kept = $manager->create([
            'name' => 'Deleted With School',
            'driver' => 'local',
            'ownership' => 'school',
            'config' => [],
        ], $school->id);

        $school->delete();
        $this->assertSoftDeleted('storage_providers', ['id' => $kept->id]);

        // Pretrashed still has older deleted_at; cascade-deleted has school.deleted_at.
        $school->refresh();
        $keptRow = StorageProvider::withTrashed()->find($kept->id);
        $preRow = StorageProvider::withTrashed()->find($pretrashed->id);
        $this->assertTrue($keptRow->deleted_at->equalTo($school->deleted_at));
        $this->assertTrue($preRow->deleted_at->lt($school->deleted_at));

        $school->restore();

        $this->assertSoftDeleted('storage_providers', ['id' => $pretrashed->id]);
        $this->assertDatabaseHas('storage_providers', [
            'id' => $kept->id,
            'deleted_at' => null,
            'ownership' => 'school',
            'school_id' => $school->id,
        ]);
    }

    public function test_s3_disk_config_applies_curl_resolve_pin_for_literal_endpoint(): void
    {
        $tester = app(StorageProviderConnectionTester::class);

        [$diskConfig, $error] = $tester->buildS3DiskConfig([
            'key' => 'AKIAEXAMPLE',
            'secret' => 'secret',
            'region' => 'us-east-1',
            'bucket' => 'test-bucket',
            'endpoint' => 'https://8.8.8.8:443',
            'use_path_style_endpoint' => true,
        ]);

        $this->assertNull($error);
        $this->assertIsArray($diskConfig);
        $this->assertSame('https://8.8.8.8:443', $diskConfig['endpoint']);
        $this->assertArrayHasKey('http', $diskConfig);
        $this->assertFalse($diskConfig['http']['allow_redirects']);
        $this->assertArrayHasKey('curl', $diskConfig['http']);
        $this->assertArrayHasKey(\CURLOPT_RESOLVE, $diskConfig['http']['curl']);
        $this->assertContains('8.8.8.8:443:8.8.8.8', $diskConfig['http']['curl'][\CURLOPT_RESOLVE]);
    }

    public function test_s3_disk_config_rejects_unpinnable_custom_endpoint(): void
    {
        $tester = app(StorageProviderConnectionTester::class);

        [$diskConfig, $error] = $tester->buildS3DiskConfig([
            'key' => 'AKIAEXAMPLE',
            'secret' => 'secret',
            'region' => 'us-east-1',
            'bucket' => 'test-bucket',
            'endpoint' => 'https://127.0.0.1:9000',
        ]);

        $this->assertNull($diskConfig);
        $this->assertNotNull($error);
        $this->assertStringContainsString('not permitted', $error);
    }
}
