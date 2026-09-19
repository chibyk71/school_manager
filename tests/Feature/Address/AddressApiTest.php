<?php

/**
 * Address Module Phase 4 — HTTP API feature tests.
 *
 * Authorization: owner view → list; owner update → all mutations.
 * Ownership isolation: address IDs resolved only through owner relationship.
 * Primary invariants preserved via HasAddress.
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildAddressApiSchema();
});

afterEach(function () {
    rollbackAddressApiSchema();
});

function buildAddressApiSchema(): void
{
    Schema::dropIfExists('addresses');
    Schema::dropIfExists('schools');
    Schema::dropIfExists('users');
    Schema::dropIfExists('model_has_permissions');
    Schema::dropIfExists('model_has_roles');
    Schema::dropIfExists('role_has_permissions');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('roles');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('countries');
    Schema::dropIfExists('states');
    Schema::dropIfExists('cities');

    Schema::create('countries', function (Blueprint $t) {
        $t->id();
        $t->string('name')->nullable();
        $t->string('iso2', 2)->nullable();
    });
    Schema::create('states', function (Blueprint $t) {
        $t->id();
        $t->foreignId('country_id')->nullable();
        $t->string('name')->nullable();
    });
    Schema::create('cities', function (Blueprint $t) {
        $t->id();
        $t->foreignId('state_id')->nullable();
        $t->string('name')->nullable();
    });

    Schema::create('dynamic_enums', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('applies_to')->nullable();
        $t->json('options')->nullable();
        $t->timestamps();
    });

    // Seed address type enum values used by InDynamicEnum
    DB::table('dynamic_enums')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'type',
        'applies_to' => Address::class,
        'options' => json_encode([
            ['value' => 'residential', 'label' => 'Residential'],
            ['value' => 'school_campus', 'label' => 'School Campus'],
            ['value' => 'office', 'label' => 'Office'],
            ['value' => 'other', 'label' => 'Other'],
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Schema::create('permissions', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('guard_name')->default('web');
        $t->timestamps();
    });
    Schema::create('roles', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('guard_name')->default('web');
        $t->timestamps();
    });
    Schema::create('model_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->uuid('model_id');
        $t->primary(['permission_id', 'model_id', 'model_type']);
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->uuid('model_id');
        $t->primary(['role_id', 'model_id', 'model_type']);
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    Schema::create('users', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
        $t->timestamps();
    });

    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('slug')->nullable();
        $t->string('code')->nullable();
        $t->string('email')->nullable();
        $t->string('type')->nullable();
        $t->boolean('is_active')->default(true);
        $t->json('data')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('addresses', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuidMorphs('addressable');
        $t->unsignedBigInteger('country_id')->nullable();
        $t->unsignedBigInteger('state_id')->nullable();
        $t->unsignedBigInteger('city_id')->nullable();
        $t->string('address_line_1');
        $t->string('address_line_2')->nullable();
        $t->string('landmark')->nullable();
        $t->string('city_text')->nullable();
        $t->string('postal_code')->nullable();
        $t->string('type');
        $t->decimal('latitude', 10, 7)->nullable();
        $t->decimal('longitude', 10, 7)->nullable();
        $t->boolean('is_primary')->default(false);
        $t->timestamps();
    });
}

function rollbackAddressApiSchema(): void
{
    Schema::dropIfExists('addresses');
    Schema::dropIfExists('schools');
    Schema::dropIfExists('users');
    Schema::dropIfExists('model_has_permissions');
    Schema::dropIfExists('model_has_roles');
    Schema::dropIfExists('role_has_permissions');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('roles');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('countries');
    Schema::dropIfExists('states');
    Schema::dropIfExists('cities');
}

function makeSchool(array $attrs = []): School
{
    return School::create(array_merge([
        'id' => (string) Str::uuid(),
        'name' => 'Test School',
        'slug' => 'test-school-'.Str::random(4),
        'code' => 'TS'.Str::random(3),
        'email' => Str::random(6).'@example.com',
        'type' => 'private',
        'is_active' => true,
    ], $attrs));
}

function makeUser(): User
{
    return User::create([
        'id' => (string) Str::uuid(),
        'name' => 'Test User',
        'email' => Str::random(6).'@example.com',
    ]);
}

function actingAsWithSchoolAccess(User $user, School $school, bool $canUpdate = true): void
{
    // Override SchoolPolicy gates for focused schema tests.
    Gate::define('view', fn (User $u, $model) => true);
    Gate::define('update', fn (User $u, $model) => $canUpdate);
    $this->actingAs($user);
}

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

it('allows listing addresses when owner view is granted', function () {
    $school = makeSchool();
    $user = makeUser();
    Gate::define('view', fn () => true);
    Gate::define('update', fn () => false);
    $this->actingAs($user);

    $school->addAddress(['address_line_1' => '1 Main', 'type' => 'residential']);

    $this->getJson(route('addresses.index', ['owner' => 'school', 'ownerId' => $school->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('denies listing addresses without owner view', function () {
    $school = makeSchool();
    $user = makeUser();
    Gate::define('view', fn () => false);
    Gate::define('update', fn () => false);
    $this->actingAs($user);

    $this->getJson(route('addresses.index', ['owner' => 'school', 'ownerId' => $school->id]))
        ->assertForbidden();
});

it('allows create update delete set and unset primary with owner update', function () {
    $school = makeSchool();
    $user = makeUser();
    Gate::define('view', fn () => true);
    Gate::define('update', fn () => true);
    $this->actingAs($user);

    // Create (not auto-primary)
    $create = $this->postJson(route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]), [
        'address_line_1' => '10 Campus Rd',
        'type' => 'school_campus',
    ])->assertCreated()->json('data');

    expect($create['is_primary'])->toBeFalse();
    expect($create)->not->toHaveKey('addressable_type');
    expect($create)->not->toHaveKey('addressable_id');

    $id = $create['id'];

    // Update
    $this->patchJson(route('addresses.update', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]), [
        'address_line_1' => '12 Campus Rd',
    ])->assertOk()->assertJsonPath('data.address_line_1', '12 Campus Rd');

    // Set primary
    $this->postJson(route('addresses.set-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]))
        ->assertOk()
        ->assertJsonPath('data.is_primary', true);

    // Unset primary
    $this->deleteJson(route('addresses.unset-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]))
        ->assertNoContent();

    expect($school->fresh()->primaryAddress())->toBeNull();

    // Delete
    $this->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]))
        ->assertNoContent();

    expect($school->addresses()->count())->toBe(0);
});

it('denies mutations with view-only access', function () {
    $school = makeSchool();
    $user = makeUser();
    Gate::define('view', fn () => true);
    Gate::define('update', fn () => false);
    $this->actingAs($user);

    $addr = $school->addAddress(['address_line_1' => '1 Main', 'type' => 'residential']);

    $this->postJson(route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]), [
        'address_line_1' => '2 Main',
        'type' => 'residential',
    ])->assertForbidden();

    $this->patchJson(route('addresses.update', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $addr->id]), [
        'address_line_1' => 'Changed',
    ])->assertForbidden();

    $this->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $addr->id]))
        ->assertForbidden();

    $this->postJson(route('addresses.set-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $addr->id]))
        ->assertForbidden();

    $this->deleteJson(route('addresses.unset-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $addr->id]))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Ownership isolation
// ---------------------------------------------------------------------------

it('cannot mutate an address belonging to another owner', function () {
    $a = makeSchool(['name' => 'School A']);
    $b = makeSchool(['name' => 'School B']);
    $user = makeUser();
    Gate::define('view', fn () => true);
    Gate::define('update', fn () => true);
    $this->actingAs($user);

    $addrOnB = $b->addAddress(['address_line_1' => 'B Street', 'type' => 'office']);

    $this->patchJson(route('addresses.update', ['owner' => 'school', 'ownerId' => $a->id, 'address' => $addrOnB->id]), [
        'address_line_1' => 'Hijacked',
    ])->assertNotFound();

    $this->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $a->id, 'address' => $addrOnB->id]))
        ->assertNotFound();

    $this->postJson(route('addresses.set-primary', ['owner' => 'school', 'ownerId' => $a->id, 'address' => $addrOnB->id]))
        ->assertNotFound();

    $this->deleteJson(route('addresses.unset-primary', ['owner' => 'school', 'ownerId' => $a->id, 'address' => $addrOnB->id]))
        ->assertNotFound();
});

it('rejects unsupported owner aliases', function () {
    $user = makeUser();
    Gate::define('view', fn () => true);
    Gate::define('update', fn () => true);
    $this->actingAs($user);

    $this->getJson(route('addresses.index', ['owner' => 'arbitrary_model', 'ownerId' => (string) Str::uuid()]))
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Primary behavior
// ---------------------------------------------------------------------------

it('preserves primary invariants via API', function () {
    $school = makeSchool();
    $user = makeUser();
    Gate::define('view', fn () => true);
    Gate::define('update', fn () => true);
    $this->actingAs($user);

    $first = $this->postJson(route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]), [
        'address_line_1' => 'First',
        'type' => 'residential',
    ])->assertCreated()->json('data');

    expect($first['is_primary'])->toBeFalse();

    $second = $this->postJson(route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]), [
        'address_line_1' => 'Second',
        'type' => 'office',
        'is_primary' => true,
    ])->assertCreated()->json('data');

    expect($second['is_primary'])->toBeTrue();

    // Promote first → clears second
    $this->postJson(route('addresses.set-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $first['id']]))
        ->assertOk()
        ->assertJsonPath('data.is_primary', true);

    expect($school->addresses()->where('is_primary', true)->count())->toBe(1);
    expect($school->addresses()->whereKey($second['id'])->value('is_primary'))->toBeFalsy();

    // Delete primary does not promote
    $this->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $first['id']]))
        ->assertNoContent();

    expect($school->fresh()->primaryAddress())->toBeNull();
    expect($school->addresses()->count())->toBe(1);
});
