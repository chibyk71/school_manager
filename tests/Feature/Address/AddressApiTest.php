<?php

/**
 * Address Module Phase 4 — HTTP API feature tests.
 *
 * Authorization: owner view → list; owner update → all mutations.
 * Ownership isolation: address IDs resolved only through owner relationship.
 * Primary invariants preserved via HasAddress.
 *
 * TestCase is bound via tests/Pest.php → pest()->extend(...)->in('Feature');
 * Do not call uses(Tests\TestCase::class) here or Pest will double-bind.
 */

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

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
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

    Schema::create('permissions', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('model_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
        $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
    });

    Schema::create('model_has_roles', function (Blueprint $table) {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
        $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
    });

    Schema::create('role_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
        $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
    });

    Schema::create('dynamic_enums', function (Blueprint $table) {
        $table->id();
        $table->string('model');
        $table->string('property');
        $table->string('value');
        $table->string('label')->nullable();
        $table->timestamps();
    });

    DB::table('dynamic_enums')->insert([
        ['model' => 'App\\Models\\Address', 'property' => 'type', 'value' => 'physical', 'label' => 'Physical'],
        ['model' => 'App\\Models\\Address', 'property' => 'type', 'value' => 'postal', 'label' => 'Postal'],
        ['model' => 'App\\Models\\Address', 'property' => 'type', 'value' => 'billing', 'label' => 'Billing'],
    ]);
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

function makeUserWithSchoolPerms(School $school, array $abilities = ['view', 'update']): User
{
    $user = User::query()->create([
        'name' => 'Tester',
        'email' => 'tester-'.Str::random(6).'@example.com',
        'password' => bcrypt('password'),
    ]);

    foreach ($abilities as $ability) {
        Gate::define($ability, function ($authUser, $model) use ($user, $school, $ability) {
            if ($authUser->id !== $user->id) {
                return false;
            }
            if ($model instanceof School) {
                return $model->id === $school->id;
            }

            return false;
        });
    }

    return $user;
}

it('allows listing addresses when owner view is granted', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolPerms($school, ['view']);

    $school->addAddress(['address_line_1' => '12 Main St', 'type' => 'physical'], false);

    $this->actingAs($user)
        ->getJson(route('addresses.index', ['owner' => 'school', 'ownerId' => $school->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('denies listing addresses without owner view', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolPerms($school, []); // no abilities

    $this->actingAs($user)
        ->getJson(route('addresses.index', ['owner' => 'school', 'ownerId' => $school->id]))
        ->assertForbidden();
});

it('allows create update delete set and unset primary with owner update', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolPerms($school, ['view', 'update']);

    $create = $this->actingAs($user)
        ->postJson(route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]), [
            'address_line_1' => '1 First Ave',
            'type' => 'physical',
            'is_primary' => false,
        ])
        ->assertCreated();

    $id = $create->json('data.id') ?? $create->json('id');
    expect($id)->not->toBeEmpty();

    $this->actingAs($user)
        ->putJson(route('addresses.update', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]), [
            'address_line_1' => '1 First Ave Updated',
            'type' => 'physical',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('addresses.set-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]))
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('addresses.unset-primary', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]))
        ->assertOk();

    $this->actingAs($user)
        ->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $id]))
        ->assertOk();
});

it('denies mutations with view-only access', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolPerms($school, ['view']);

    $address = $school->addAddress(['address_line_1' => 'Read Only St', 'type' => 'physical'], false);

    $this->actingAs($user)
        ->postJson(route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]), [
            'address_line_1' => 'Should Fail',
            'type' => 'physical',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->putJson(route('addresses.update', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $address->id]), [
            'address_line_1' => 'Nope',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $address->id]))
        ->assertForbidden();
});

it('cannot mutate an address belonging to another owner', function () {
    $schoolA = makeSchool(['name' => 'School A']);
    $schoolB = makeSchool(['name' => 'School B']);
    $user = makeUserWithSchoolPerms($schoolA, ['view', 'update']);

    $foreign = $schoolB->addAddress(['address_line_1' => 'Foreign Rd', 'type' => 'physical'], false);

    $this->actingAs($user)
        ->putJson(route('addresses.update', ['owner' => 'school', 'ownerId' => $schoolA->id, 'address' => $foreign->id]), [
            'address_line_1' => 'Hijack',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $schoolA->id, 'address' => $foreign->id]))
        ->assertNotFound();
});

it('rejects unsupported owner aliases', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolPerms($school, ['view', 'update']);

    $this->actingAs($user)
        ->getJson(route('addresses.index', ['owner' => 'not-a-real-owner', 'ownerId' => $school->id]))
        ->assertStatus(404);
});

it('preserves primary invariants via API', function () {
    $school = makeSchool();
    $user = makeUserWithSchoolPerms($school, ['view', 'update']);

    $a = $this->actingAs($user)
        ->postJson(route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]), [
            'address_line_1' => 'Alpha',
            'type' => 'physical',
            'is_primary' => true,
        ])
        ->assertCreated();

    $b = $this->actingAs($user)
        ->postJson(route('addresses.store', ['owner' => 'school', 'ownerId' => $school->id]), [
            'address_line_1' => 'Beta',
            'type' => 'physical',
            'is_primary' => true,
        ])
        ->assertCreated();

    $idA = $a->json('data.id') ?? $a->json('id');
    $idB = $b->json('data.id') ?? $b->json('id');

    // Only one primary
    $list = $this->actingAs($user)
        ->getJson(route('addresses.index', ['owner' => 'school', 'ownerId' => $school->id]))
        ->assertOk()
        ->json('data');

    $primaries = collect($list)->where('is_primary', true)->count();
    expect($primaries)->toBe(1);

    // Delete primary leaves zero primary (no auto-promotion)
    $this->actingAs($user)
        ->deleteJson(route('addresses.destroy', ['owner' => 'school', 'ownerId' => $school->id, 'address' => $idB]))
        ->assertOk();

    $list2 = $this->actingAs($user)
        ->getJson(route('addresses.index', ['owner' => 'school', 'ownerId' => $school->id]))
        ->assertOk()
        ->json('data');

    expect(collect($list2)->where('is_primary', true)->count())->toBe(0);
});
