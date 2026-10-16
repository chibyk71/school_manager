<?php

namespace Tests\Feature\Storage;

use App\Models\Permission;
use App\Models\School;
use App\Models\User;
use App\Services\Storage\StorageProviderManager;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use ReflectionObject;
use Tests\Support\StorageProviderTestSchema;
use Tests\TestCase;

/**
 * HTTP authorization and isolation coverage for storage providers.
 *
 * School context is established via the real schoolManager binding
 * (GetSchoolModel → app('schoolManager')->getActiveSchool()).
 *
 * Focused schema only — avoids broken devices SQLite migrations.
 * Gate::before (Laratrust) is cleared so StorageProviderPolicy receives
 * policy abilities (create, testConnection), matching DynamicEnum HTTP tests.
 */
class StorageProviderHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        StorageProviderTestSchema::build();

        // Laratrust Gate::before treats policy abilities (create, testConnection) as
        // Laratrust permission names and queries roles — clear so StorageProviderPolicy runs.
        $gate = Gate::getFacadeRoot();
        $ref = new ReflectionObject($gate);
        $prop = $ref->getProperty('beforeCallbacks');
        $prop->setAccessible(true);
        $prop->setValue($gate, []);

        $this->withoutMiddleware([
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\EnsureCurrentSession::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        StorageProviderTestSchema::drop();
        Model::reguard();
        parent::tearDown();
    }

    private function makeSchool(string $name = 'HTTP School'): School
    {
        return School::query()->create([
            'name' => $name,
            'code' => strtoupper(substr(md5($name.microtime(true)), 0, 8)),
            'slug' => str($name)->slug().'-'.substr(md5($name.microtime(true)), 0, 6),
            'email' => str($name)->slug().'-'.substr(md5((string) microtime(true)), 0, 6).'@test.example',
            'type' => 'private',
        ]);
    }

    private function grant(User $user, array $permissions, ?School $school = null): void
    {
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['display_name' => $name]
            );

            DB::table('permission_user')->insertOrIgnore([
                'permission_id' => $permission->id,
                'user_id' => $user->id,
                'user_type' => User::class,
                'school_id' => $school?->id,
            ]);
            if ($school !== null) {
                DB::table('permission_user')->insertOrIgnore([
                    'permission_id' => $permission->id,
                    'user_id' => $user->id,
                    'user_type' => User::class,
                    'school_id' => null,
                ]);
            }
        }
    }

    private function bindActiveSchool(?School $school): void
    {
        $manager = new class($school)
        {
            public function __construct(private $school) {}

            public function getActiveSchool(?\Illuminate\Http\Request $request = null): ?School
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

        if (function_exists('GetSchoolModel')) {
            $resolved = GetSchoolModel();
            if ($school !== null) {
                $this->assertNotNull($resolved, 'GetSchoolModel must resolve the bound schoolManager school');
                $this->assertSame($school->id, $resolved->id);
            } else {
                $this->assertNull($resolved);
            }
        }
    }

    private function makeUser(): User
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Storage Tester',
            'email' => 'storage-'.substr(md5($id), 0, 8).'@test.example',
            'password' => bcrypt('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function actingManager(?School $school = null): User
    {
        $user = $this->makeUser();
        $this->grant($user, ['storage-providers.view', 'storage-providers.manage'], $school);
        $this->bindActiveSchool($school);

        return $user;
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->get(route('settings.storage-providers.index'))->assertRedirect();
        $this->postJson(route('settings.storage-providers.store'), [])->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_list(): void
    {
        $user = $this->makeUser();
        $school = $this->makeSchool('No Perm');
        $this->bindActiveSchool($school);

        $this->actingAs($user)
            ->get(route('settings.storage-providers.index'))
            ->assertForbidden();
    }

    public function test_school_user_can_create_school_provider_when_allowed(): void
    {
        $school = $this->makeSchool('Create School OK');
        $user = $this->actingManager($school);

        $this->actingAs($user)
            ->postJson(route('settings.storage-providers.store'), [
                'name' => 'School Disk',
                'driver' => 'local',
                'ownership' => 'school',
                'config' => [],
                'enabled' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('provider.ownership', 'school')
            ->assertJsonPath('provider.name', 'School Disk');

        $this->assertDatabaseHas('storage_providers', [
            'name' => 'School Disk',
            'ownership' => 'school',
            'school_id' => $school->id,
        ]);
    }

    public function test_school_provider_management_disabled_rejects_create_with_403(): void
    {
        $school = $this->makeSchool('Policy Off');
        $user = $this->actingManager($school);

        $resolver = app(SettingsResolver::class);
        $resolver->set(
            'file_manager.allow_school_provider_management',
            false,
            SettingsScope::school($school->id)
        );

        $this->bindActiveSchool($school);

        $this->actingAs($user)
            ->postJson(route('settings.storage-providers.store'), [
                'name' => 'Blocked Disk',
                'driver' => 'local',
                'ownership' => 'school',
                'config' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('storage_providers', ['name' => 'Blocked Disk']);
    }

    public function test_school_user_cannot_view_system_provider_by_id(): void
    {
        $school = $this->makeSchool('View System Block');
        $manager = app(StorageProviderManager::class);

        $system = $manager->create([
            'name' => 'Platform Disk',
            'driver' => 'local',
            'ownership' => 'system',
            'config' => [],
        ], null);

        $user = $this->actingManager($school);

        $this->actingAs($user)
            ->getJson(route('settings.storage-providers.show', $system->id))
            ->assertNotFound();
    }

    public function test_school_user_cannot_view_or_update_other_school_provider(): void
    {
        $schoolA = $this->makeSchool('A Isolation');
        $schoolB = $this->makeSchool('B Isolation');
        $manager = app(StorageProviderManager::class);

        $providerB = $manager->create([
            'name' => 'B Disk',
            'driver' => 'local',
            'ownership' => 'school',
            'config' => [],
        ], $schoolB->id);

        $user = $this->actingManager($schoolA);

        $this->actingAs($user)
            ->getJson(route('settings.storage-providers.show', $providerB->id))
            ->assertNotFound();

        $this->actingAs($user)
            ->patchJson(route('settings.storage-providers.update', $providerB->id), [
                'name' => 'Hacked',
            ])
            ->assertNotFound();
    }

    public function test_school_user_cannot_create_or_update_system_provider(): void
    {
        $school = $this->makeSchool('System Mutate Block');
        $manager = app(StorageProviderManager::class);

        $system = $manager->create([
            'name' => 'Sys Locked',
            'driver' => 'local',
            'ownership' => 'system',
            'config' => [],
        ], null);

        $user = $this->actingManager($school);

        $this->actingAs($user)
            ->postJson(route('settings.storage-providers.store'), [
                'name' => 'Should Fail',
                'driver' => 'local',
                'ownership' => 'system',
                'config' => [],
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->patchJson(route('settings.storage-providers.update', $system->id), [
                'name' => 'Hacked',
            ])
            ->assertNotFound();
    }

    public function test_platform_context_can_create_system_provider(): void
    {
        $user = $this->actingManager(null);

        $this->actingAs($user)
            ->postJson(route('settings.storage-providers.store'), [
                'name' => 'Platform Disk',
                'driver' => 'local',
                'ownership' => 'system',
                'config' => [],
                'enabled' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('provider.ownership', 'system');

        $this->assertDatabaseHas('storage_providers', [
            'name' => 'Platform Disk',
            'ownership' => 'system',
            'school_id' => null,
        ]);
    }

    public function test_platform_context_cannot_create_school_provider(): void
    {
        $user = $this->actingManager(null);

        $this->actingAs($user)
            ->postJson(route('settings.storage-providers.store'), [
                'name' => 'Orphan School Disk',
                'driver' => 'local',
                'ownership' => 'school',
                'config' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('storage_providers', ['name' => 'Orphan School Disk']);
    }

    public function test_connection_test_system_blocked_in_school_context(): void
    {
        $school = $this->makeSchool('Test Conn Block');
        $user = $this->actingManager($school);

        $this->actingAs($user)
            ->postJson(route('settings.storage-providers.test-connection'), [
                'driver' => 'local',
                'ownership' => 'system',
                'config' => ['root' => storage_path('app/private')],
            ])
            ->assertForbidden();
    }

    public function test_connection_test_school_allowed_in_school_context(): void
    {
        $school = $this->makeSchool('Test Conn OK');
        $user = $this->actingManager($school);

        $this->actingAs($user)
            ->postJson(route('settings.storage-providers.test-connection'), [
                'driver' => 'local',
                'ownership' => 'school',
                'config' => ['root' => storage_path('app/private')],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_dependency_check_hidden_for_system_in_school_context(): void
    {
        $school = $this->makeSchool('Deps HTTP');
        $manager = app(StorageProviderManager::class);

        $system = $manager->create([
            'name' => 'Sys Deps',
            'driver' => 'local',
            'ownership' => 'system',
            'config' => [],
        ], null);

        $user = $this->actingManager($school);

        $this->actingAs($user)
            ->getJson(route('settings.storage-providers.dependencies', $system->id))
            ->assertNotFound();
    }
}
