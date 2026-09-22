<?php

/**
 * Address Module Phase 4 — HTTP API tests (focused schema, no RefreshDatabase).
 *
 * Matches production AddressController / routes/address.php contract:
 * - PATCH update
 * - 204 on destroy and unset-primary
 * - 422 on unsupported owner alias (ValidationException from resolveOwner)
 * - Owner Gate view/update (no AddressPolicy)
 * - Address IDs resolved only through owner relationship
 *
 * Fixture notes:
 * - Users are UUID (HasUuids); schools include slug (School boot).
 * - dynamic_enums uses the real applies_to/options shape (same as AddressCapabilityDomainTest).
 * - Empty Laratrust roles/permission tables so Gate::before Laratrust callback does not 500;
 *   Laratrust returns false→null and our test Gate::before decides view/update.
 * - Web middleware that needs full app routes/tables is disabled (Inertia share,
 *   EnsureCurrentSession → academic.session.index, SchoolContext, maintenance).
 */

uses(Tests\TestCase::class);

use App\Models\Address;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildAddressApiSchema();

    $this->withoutMiddleware([
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\EnsureCurrentSession::class,
        \App\Http\Middleware\SchoolContext::class,
        \App\Http\Middleware\CheckMaintenanceMode::class,
    ]);
});

afterEach(function () {
    rollbackAddressApiSchema();
});

function buildAddressApiSchema(): void
{
    Schema::dropIfExists('permission_role');
    Schema::dropIfExists('permission_user');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('role_user');
    Schema::dropIfExists('roles');
    Schema::dropIfExists('addresses');
    Schema::dropIfExists('schools');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('users');
    Schema::dropIfExists('dynamic_enums');

    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('profiles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('user_id')->nullable()->index();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('full_name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug')->nullable()->unique();
        $table->string('code')->nullable();
        $table->string('email')->nullable();
        $table->string('phone_one')->nullable();
        $table->string('phone_two')->nullable();
        $table->string('type')->nullable();
        $table->boolean('is_active')->default(true);
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('addresses', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuidMorphs('addressable');
        $table->string('type')->nullable();
        $table->string('address_line_1');
        $table->string('address_line_2')->nullable();
        $table->string('landmark')->nullable();
        $table->string('postal_code')->nullable();
        $table->unsignedBigInteger('country_id')->nullable();
        $table->unsignedBigInteger('state_id')->nullable();
        $table->unsignedBigInteger('city_id')->nullable();
        $table->string('city_text')->nullable();
        $table->boolean('is_primary')->default(false);
        $table->timestamps();
    });

    Schema::create('dynamic_enums', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('label')->nullable();
        $table->string('applies_to')->nullable();
        $table->text('description')->nullable();
        $table->string('color')->nullable();
        $table->json('options')->nullable();
        $table->uuid('school_id')->nullable();
        $table->timestamps();
    });

    DB::table('dynamic_enums')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'type',
        'label' => 'Address Type',
        'applies_to' => Address::class,
        'options' => json_encode([
            ['value' => 'physical', 'label' => 'Physical'],
            ['value' => 'postal', 'label' => 'Postal'],
            ['value' => 'billing', 'label' => 'Billing'],
        ]),
        'school_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Schema::create('roles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->unique();
        $table->string('display_name')->nullable();
        $table->string('description')->nullable();
        $table->uuid('school_id')->nullable();
        $table->timestamps();
    });
    Schema::create('role_user', function (Blueprint $table) {
        $table->uuid('role_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->string('school_section_id')->nullable();
    });
    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->string('display_name')->nullable();
        $table->string('description')->nullable();
        $table->timestamps();
    });
    Schema::create('permission_user', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->string('school_section_id')->nullable();
    });
    Schema::create('permission_role', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('role_id');
    });
}

function rollbackAddressApiSchema(): void
{
    Schema::dropIfExists('permission_role');
    Schema::dropIfExists('permission_user');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('role_user');
    Schema::dropIfExists('roles');
    Schema::dropIfExists('addresses');
    Schema::dropIfExists('schools');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('users');
    Schema::dropIfExists('dynamic_enums');
}

function makeSchool(array $overrides = []): School
{
    return School::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'name' => 'Test School '.Str::random(4),
        'code' => 'TS'.Str::random(3),
        'email' => 'school@example.com',
        'type' => 'private',
        'is_active' => true,
    ], $overrides));
}

function makeUserWithSchoolAbilities(School $school, array $abilities = ['view', 'update']): User
{
    $user = User::query()->create([
        'name' => 'Tester',
        'email' => 'tester-'.Str::random(6).'@example.com',
        'password' => bcrypt('password'),
    ]);

    $userId = $user->id;
    $schoolId = $school->id;

    Gate::before(function (User $auth, string $ability, mixed $arguments = null) use ($userId, $schoolId, $abilities) {
        if ($auth->id !== $userId) {
            return null;
        }
        if (! in_array($ability, $abilities, true)) {
            return false;
        }
        $model = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;
        if ($model instanceof School) {
            return $model->id === $schoolId;
        }

        return null;
    });

    return $user;
}

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'address_line_1' => '12 Main Street',
        'type' => 'physical',
        'is_primary' => false,
    ], $overrides);
}

function assertAddressResourceShape(array $row): void
{
    expect($row)->toHaveKeys([
        'id',
        'type',
        'address_line_1',
        'address_line_2',
        'landmark',
        'postal_code',
        'country_id',
        'state_id',
        'city_id',
        'city_text',
        'is_primary',
        'formatted',
    ]);
    expect($row)->not->toHaveKey('addressable_type');
    expect($row)->not->toHaveKey('addressable_id');
    expect($row)->not->toHaveKey('latitude');
    expect($row)->not->toHaveKey('longitude');
}

it('allows listing addresses when owner view is granted', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolAbilities($school, ['view']);

    $school->addAddress(addressPayload(['address_line_1' => 'Listed Ave']), false);

    $response = $this->actingAs($user)
        ->getJson(route('addresses.index', ['owner' => 'school', 'ownerId' => $school->id]))
        ->assertOk();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    assertAddressResourceShape($data[0]);
});

it('denies listing addresses without owner view', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolAbilities($school, []);

    $this->actingAs($user)
        ->getJson(route('addresses.index', ['owner' => 'school', 'ownerId' => $school->id]))
        ->assertForbidden();
});

it('allows create update delete set and unset primary with owner update', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolAbilities($school, ['view', 'update']);

    $create = $this->actingAs($user)
        ->postJson(
            route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]),
            addressPayload(['address_line_1' => '1 First Ave'])
        )
        ->assertCreated();

    $body = $create->json('data') ?? $create->json();
    assertAddressResourceShape($body);
    $id = $body['id'];
    expect($id)->not->toBeEmpty();
    expect($body['is_primary'])->toBeFalse();

    $update = $this->actingAs($user)
        ->patchJson(
            route('addresses.update', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]),
            ['address_line_1' => '1 First Ave Updated', 'type' => 'physical']
        )
        ->assertOk();

    $updated = $update->json('data') ?? $update->json();
    expect($updated['address_line_1'])->toBe('1 First Ave Updated');

    $setPrimary = $this->actingAs($user)
        ->postJson(route('addresses.set-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]))
        ->assertOk();

    $primaryBody = $setPrimary->json('data') ?? $setPrimary->json();
    expect($primaryBody['is_primary'])->toBeTrue();

    $this->actingAs($user)
        ->deleteJson(route('addresses.unset-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]))
        ->assertNoContent();

    $this->actingAs($user)
        ->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]))
        ->assertNoContent();

    expect($school->addresses()->count())->toBe(0);
});

it('denies mutations with view-only access', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolAbilities($school, ['view']);

    $address = $school->addAddress(addressPayload(['address_line_1' => 'Read Only St']), false);

    $this->actingAs($user)
        ->postJson(
            route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]),
            addressPayload(['address_line_1' => 'Should Fail'])
        )
        ->assertForbidden();

    $this->actingAs($user)
        ->patchJson(
            route('addresses.update', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $address->id]),
            ['address_line_1' => 'Nope']
        )
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('addresses.set-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $address->id]))
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('addresses.unset-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $address->id]))
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $address->id]))
        ->assertForbidden();
});

it('cannot mutate an address belonging to another owner', function () {
    $schoolA = makeSchool(['name' => 'School A']);
    $schoolB = makeSchool(['name' => 'School B']);
    $user = makeUserWithSchoolAbilities($schoolA, ['view', 'update']);

    $foreign = $schoolB->addAddress(addressPayload(['address_line_1' => 'Foreign Rd']), false);

    $this->actingAs($user)
        ->patchJson(
            route('addresses.update', ['owner' => 'school', 'ownerId' => $schoolA->id, 'address' => $foreign->id]),
            ['address_line_1' => 'Hijack']
        )
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson(route('addresses.set-primary', ['owner' => 'school', 'ownerId' => $schoolA->id, 'address' => $foreign->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson(route('addresses.unset-primary', ['owner' => 'school', 'ownerId' => $schoolA->id, 'address' => $foreign->id]))
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $schoolA->id, 'address' => $foreign->id]))
        ->assertNotFound();

    expect($schoolB->addresses()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('rejects unsupported owner aliases with validation error', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolAbilities($school, ['view', 'update']);

    $this->actingAs($user)
        ->getJson(route('addresses.index', ['owner' => 'not-a-real-owner', 'ownerId' => $school->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['owner']);
});

it('preserves primary invariants via API', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolAbilities($school, ['view', 'update']);

    $a = $this->actingAs($user)
        ->postJson(
            route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]),
            addressPayload(['address_line_1' => 'Alpha', 'is_primary' => true])
        )
        ->assertCreated();

    $b = $this->actingAs($user)
        ->postJson(
            route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]),
            addressPayload(['address_line_1' => 'Beta', 'is_primary' => true])
        )
        ->assertCreated();

    $idA = ($a->json('data') ?? $a->json())['id'];
    $idB = ($b->json('data') ?? $b->json())['id'];

    $list = $this->actingAs($user)
        ->getJson(route('addresses.index', ['owner' => 'school', 'ownerId' => $school->id]))
        ->assertOk()
        ->json('data');

    expect(collect($list)->where('is_primary', true)->count())->toBe(1);
    expect(collect($list)->firstWhere('id', $idB)['is_primary'])->toBeTrue();
    expect(collect($list)->firstWhere('id', $idA)['is_primary'])->toBeFalse();

    $this->actingAs($user)
        ->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $idB]))
        ->assertNoContent();

    $list2 = $this->actingAs($user)
        ->getJson(route('addresses.index', ['owner' => 'school', 'ownerId' => $school->id]))
        ->assertOk()
        ->json('data');

    expect(collect($list2)->where('is_primary', true)->count())->toBe(0);
    expect($list2)->toHaveCount(1);
});

it('resource representation omits polymorphic ownership fields', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolAbilities($school, ['view', 'update']);

    $response = $this->actingAs($user)
        ->postJson(
            route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]),
            addressPayload(['address_line_1' => 'Shape Check', 'type' => 'billing'])
        )
        ->assertCreated();

    $row = $response->json('data') ?? $response->json();
    assertAddressResourceShape($row);
    expect($row['type'])->toBe('billing');
    expect($row['address_line_1'])->toBe('Shape Check');
});
