<?php

namespace Tests\Feature\School;

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Settings\Definitions\SchoolOperationalSettings;
use App\Support\Settings\SettingsResolver;
use App\Support\Settings\SettingsScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 2 — HTTP coverage for school operational preferences and profile edit props.
 */
class SchoolOperationalSettingsFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('schools') || ! Schema::hasTable('settings')) {
            $this->markTestSkipped('schools and settings tables required');
        }
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();

        if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
            $role = Role::query()->firstOrCreate(
                ['name' => 'super-admin'],
                ['display_name' => 'Super Admin']
            );
            DB::table('role_user')->insertOrIgnore([
                'role_id' => $role->id,
                'user_id' => $user->id,
                'user_type' => User::class,
                'school_id' => null,
            ]);
        }

        $this->actingAs($user->fresh());

        return $user;
    }

    private function actingAsUnauthorized(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function validPreferences(array $overrides = []): array
    {
        return array_merge([
            'timezone' => 'Africa/Lagos',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'week_start' => 'monday',
            'locale' => 'en',
            'default_currency' => 'NGN',
        ], $overrides);
    }

    public function test_authorized_user_can_update_operational_settings_for_route_bound_school(): void
    {
        $this->actingAsSuperAdmin();
        $school = School::factory()->create(['name' => 'Alpha Academy']);

        $response = $this->put(
            route('schools.operational-settings.update', $school),
            $this->validPreferences([
                'timezone' => 'Africa/Lagos',
                'default_currency' => 'USD',
            ])
        );

        $response->assertRedirect(route('schools.edit', $school));
        $response->assertSessionHas('success');

        $resolver = app(SettingsResolver::class);
        $scope = SettingsScope::school((string) $school->getKey());

        $this->assertSame('Africa/Lagos', $resolver->get(SchoolOperationalSettings::TIMEZONE, $scope));
        $this->assertSame('USD', $resolver->get(SchoolOperationalSettings::DEFAULT_CURRENCY, $scope));
    }

    public function test_unauthorized_user_cannot_update_operational_settings(): void
    {
        $this->actingAsUnauthorized();
        $school = School::factory()->create();

        $response = $this->put(
            route('schools.operational-settings.update', $school),
            $this->validPreferences()
        );

        $response->assertForbidden();

        $resolver = app(SettingsResolver::class);
        $scope = SettingsScope::school((string) $school->getKey());
        $this->assertSame(
            (string) config('app.timezone', 'UTC'),
            $resolver->get(SchoolOperationalSettings::TIMEZONE, $scope)
        );
    }

    public function test_client_supplied_school_id_does_not_redirect_writes_to_another_school(): void
    {
        $this->actingAsSuperAdmin();
        $target = School::factory()->create(['name' => 'Target']);
        $other = School::factory()->create(['name' => 'Other']);

        $payload = $this->validPreferences([
            'timezone' => 'Europe/London',
            'school_id' => $other->id,
            'school' => $other->id,
        ]);

        $response = $this->put(
            route('schools.operational-settings.update', $target),
            $payload
        );

        $response->assertRedirect(route('schools.edit', $target));

        $resolver = app(SettingsResolver::class);
        $this->assertSame(
            'Europe/London',
            $resolver->get(SchoolOperationalSettings::TIMEZONE, SettingsScope::school((string) $target->getKey()))
        );
        $this->assertSame(
            (string) config('app.timezone', 'UTC'),
            $resolver->get(SchoolOperationalSettings::TIMEZONE, SettingsScope::school((string) $other->getKey()))
        );
    }

    public function test_invalid_settings_leave_existing_overrides_unchanged(): void
    {
        $this->actingAsSuperAdmin();
        $school = School::factory()->create();
        $scope = SettingsScope::school((string) $school->getKey());
        $resolver = app(SettingsResolver::class);

        $resolver->set(SchoolOperationalSettings::TIMEZONE, 'Africa/Lagos', $scope);
        $resolver->set(SchoolOperationalSettings::DEFAULT_CURRENCY, 'NGN', $scope);

        $response = $this->from(route('schools.edit', $school))->put(
            route('schools.operational-settings.update', $school),
            $this->validPreferences([
                'timezone' => 'Not/A_Real_Zone',
                'default_currency' => 'XXXX',
            ])
        );

        $response->assertSessionHasErrors(['timezone', 'default_currency']);

        $this->assertSame('Africa/Lagos', $resolver->get(SchoolOperationalSettings::TIMEZONE, $scope));
        $this->assertSame('NGN', $resolver->get(SchoolOperationalSettings::DEFAULT_CURRENCY, $scope));
    }

    public function test_profile_edit_page_receives_school_and_operational_settings_props(): void
    {
        $this->actingAsSuperAdmin();
        $school = School::factory()->create(['name' => 'Prop School', 'code' => 'PROP1']);
        $scope = SettingsScope::school((string) $school->getKey());
        app(SettingsResolver::class)->set(SchoolOperationalSettings::TIMEZONE, 'Africa/Nairobi', $scope);

        $response = $this->get(route('schools.edit', $school));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/School/Profile')
            ->has('school', fn ($s) => $s
                ->where('id', $school->id)
                ->where('name', 'Prop School')
                ->etc()
            )
            ->has('operationalSettings', fn ($ops) => $ops
                ->where('timezone', 'Africa/Nairobi')
                ->where('date_format', 'd/m/Y')
                ->etc()
            )
            ->has('operationalOptions.date_formats')
            ->has('operationalOptions.currencies')
        );
    }

    public function test_authorized_user_can_update_school_identity_via_profile_update_route(): void
    {
        $this->actingAsSuperAdmin();
        $school = School::factory()->create([
            'name' => 'Old Name',
            'contact_name' => 'Old Contact',
        ]);

        $response = $this->put(route('schools.update', $school), [
            'name' => 'New Name',
            'contact_name' => 'New Contact',
            'email' => $school->email,
            'phone_one' => $school->phone_one,
            'code' => $school->code,
        ]);

        $response->assertRedirect();
        $school->refresh();
        $this->assertSame('New Name', $school->name);
        $this->assertSame('New Contact', $school->contact_name);
    }

    public function test_post_without_method_spoof_to_update_is_not_the_profile_contract(): void
    {
        $this->actingAsSuperAdmin();
        $school = School::factory()->create(['name' => 'Unchanged']);

        $response = $this->post(route('schools.update', $school), [
            'name' => 'Should Not Apply',
        ]);

        $this->assertTrue(
            in_array($response->status(), [404, 405], true),
            'Expected 404/405 for POST to schools.update without method spoof, got '.$response->status()
        );
        $this->assertSame('Unchanged', $school->fresh()->name);
    }
}
