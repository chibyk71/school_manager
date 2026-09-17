<?php

/**
 * Address Phase 1 - foundational domain tests.
 *
 * Exercises the real create_addresses_table migration (not a hand-copied Schema::create).
 * Prerequisites (countries/states/cities/dynamic_enums/profiles) are minimal stubs required
 * by FK constraints and ownership; the addresses table itself comes only from the migration.
 */

uses(Tests\TestCase::class);

use App\Models\Address;
use App\Models\Profile;
use App\Rules\InDynamicEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildAddressPhase1Prerequisites();
    runAddressMigration();
});

afterEach(function () {
    rollbackAddressMigration();
    dropAddressPhase1Prerequisites();
});

function addressMigrationPath(): string
{
    return database_path('migrations/2025_12_01_065635_create_addresses_table.php');
}

function loadAddressMigration(): object
{
    return require addressMigrationPath();
}

function runAddressMigration(): void
{
    Schema::dropIfExists('addresses');
    loadAddressMigration()->up();
}

function rollbackAddressMigration(): void
{
    if (Schema::hasTable('addresses')) {
        loadAddressMigration()->down();
    }
}

function dropAddressPhase1Prerequisites(): void
{
    Schema::dropIfExists('cities');
    Schema::dropIfExists('states');
    Schema::dropIfExists('countries');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('profiles');
}

function buildAddressPhase1Prerequisites(): void
{
    dropAddressPhase1Prerequisites();
    Schema::dropIfExists('addresses');

    Schema::create('countries', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('iso2')->nullable();
    });

    Schema::create('states', function (Blueprint $table) {
        $table->id();
        $table->foreignId('country_id')->nullable();
        $table->string('name')->nullable();
    });

    Schema::create('cities', function (Blueprint $table) {
        $table->id();
        $table->foreignId('state_id')->nullable();
        $table->string('name')->nullable();
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

    Schema::create('profiles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}

function seedAddressTypeDynamicEnum(): void
{
    // DynamicEnum uses BelongsToSchool; Eloquent create requires active school even for
    // global (school_id null) rows. DB insert matches Phase4 fixtures.
    DB::table('dynamic_enums')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'type',
        'label' => 'Address Type',
        'applies_to' => Address::class,
        'options' => json_encode([
            ['value' => 'residential', 'label' => 'Residential'],
            ['value' => 'school_campus', 'label' => 'School Campus'],
            ['value' => 'office', 'label' => 'Office'],
            ['value' => 'postal', 'label' => 'Postal'],
            ['value' => 'temporary', 'label' => 'Temporary'],
            ['value' => 'billing', 'label' => 'Billing'],
        ]),
        'school_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeProfile(array $attrs = []): Profile
{
    $p = new Profile();
    $p->forceFill(array_merge([
        'id' => (string) Str::uuid(),
        'first_name' => 'Test',
        'last_name' => 'Owner',
    ], $attrs))->save();

    return $p->fresh();
}

function makeAddress(Model $owner, array $attrs = []): Address
{
    $address = new Address();
    $address->forceFill(array_merge([
        'addressable_type' => $owner->getMorphClass(),
        'addressable_id' => $owner->getKey(),
        'address_line_1' => '12 Test Street',
        'type' => 'residential',
        'is_primary' => false,
    ], $attrs))->save();

    return $address->fresh();
}

it('migrates addresses via the real create_addresses_table migration', function () {
    expect(Schema::hasTable('addresses'))->toBeTrue();

    $columns = Schema::getColumnListing('addresses');

    expect($columns)->toContain('id')
        ->and($columns)->toContain('addressable_type')
        ->and($columns)->toContain('addressable_id')
        ->and($columns)->toContain('type')
        ->and($columns)->toContain('address_line_1')
        ->and($columns)->toContain('address_line_2')
        ->and($columns)->toContain('landmark')
        ->and($columns)->toContain('country_id')
        ->and($columns)->toContain('state_id')
        ->and($columns)->toContain('city_id')
        ->and($columns)->toContain('city_text')
        ->and($columns)->toContain('postal_code')
        ->and($columns)->toContain('latitude')
        ->and($columns)->toContain('longitude')
        ->and($columns)->toContain('is_primary')
        ->and($columns)->toContain('created_at')
        ->and($columns)->toContain('updated_at')
        ->and($columns)->not->toContain('school_id')
        ->and($columns)->not->toContain('tenant_id')
        ->and($columns)->not->toContain('deleted_at');
});

it('creates the SQLite partial unique primary index from the migration', function () {
    $driver = Schema::getConnection()->getDriverName();
    expect($driver)->toBe('sqlite');

    $indexes = collect(DB::select("PRAGMA index_list('addresses')"))
        ->pluck('name')
        ->all();

    expect($indexes)->toContain('addresses_one_primary_per_owner');

    $info = DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'addresses_one_primary_per_owner'");
    expect($info)->not->toBeEmpty();
    expect(strtolower($info[0]->sql ?? ''))->toContain('is_primary');
});

it('rolls back the addresses table via migration down()', function () {
    expect(Schema::hasTable('addresses'))->toBeTrue();
    loadAddressMigration()->down();
    expect(Schema::hasTable('addresses'))->toBeFalse();

    loadAddressMigration()->up();
});

it('defaults is_primary to false from the migration', function () {
    $owner = makeProfile();
    $id = (string) Str::uuid();

    DB::table('addresses')->insert([
        'id' => $id,
        'addressable_type' => $owner->getMorphClass(),
        'addressable_id' => $owner->getKey(),
        'address_line_1' => 'Line',
        'type' => 'residential',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('addresses')->where('id', $id)->first();
    expect((bool) $row->is_primary)->toBeFalse();
});

it('uses UUID identity and does not use SoftDeletes', function () {
    $owner = makeProfile();
    $address = makeAddress($owner);

    expect(Str::isUuid($address->id))->toBeTrue();
    expect(in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(Address::class), true))->toBeFalse();
    expect(method_exists($address, 'trashed'))->toBeFalse();
});

it('exposes addressable country state city relationships and casts', function () {
    $owner = makeProfile();
    $countryId = DB::table('countries')->insertGetId(['name' => 'Nigeria', 'iso2' => 'NG']);
    $stateId = DB::table('states')->insertGetId(['country_id' => $countryId, 'name' => 'Lagos']);
    $cityId = DB::table('cities')->insertGetId(['state_id' => $stateId, 'name' => 'Ikeja']);

    $address = makeAddress($owner, [
        'country_id' => $countryId,
        'state_id' => $stateId,
        'city_id' => $cityId,
        'is_primary' => true,
        'latitude' => 6.5244,
        'longitude' => 3.3792,
    ]);

    expect($address->addressable)->toBeInstanceOf(Profile::class)
        ->and($address->addressable->is($owner))->toBeTrue()
        ->and($address->country)->not->toBeNull()
        ->and($address->state)->not->toBeNull()
        ->and($address->city)->not->toBeNull()
        ->and($address->is_primary)->toBeTrue()
        ->and($address->latitude)->not->toBeNull()
        ->and($address->longitude)->not->toBeNull();
});

it('belongs to addressable owner via polymorphic columns only', function () {
    $owner = makeProfile();
    $address = makeAddress($owner, ['type' => 'office']);

    expect($address->addressable_type)->toBe($owner->getMorphClass())
        ->and($address->addressable_id)->toBe($owner->getKey())
        ->and(Schema::hasColumn('addresses', 'school_id'))->toBeFalse();
});

it('allows zero or one primary and multiple non-primary addresses for same owner', function () {
    $owner = makeProfile();

    makeAddress($owner, ['is_primary' => false, 'address_line_1' => 'A']);
    makeAddress($owner, ['is_primary' => false, 'address_line_1' => 'B']);
    expect(Address::where('addressable_id', $owner->id)->where('is_primary', true)->count())->toBe(0);

    makeAddress($owner, ['is_primary' => true, 'address_line_1' => 'Primary']);
    expect(Address::where('addressable_id', $owner->id)->where('is_primary', true)->count())->toBe(1);

    makeAddress($owner, ['is_primary' => false, 'address_line_1' => 'C']);
    expect(Address::where('addressable_id', $owner->id)->count())->toBe(4);
});

it('rejects a second primary address for the same owner at the database level', function () {
    $owner = makeProfile();
    makeAddress($owner, ['is_primary' => true, 'address_line_1' => 'First primary']);

    expect(fn () => makeAddress($owner, ['is_primary' => true, 'address_line_1' => 'Second primary']))
        ->toThrow(QueryException::class);
});

it('allows each owner their own primary address', function () {
    $ownerA = makeProfile(['first_name' => 'A']);
    $ownerB = makeProfile(['first_name' => 'B']);

    makeAddress($ownerA, ['is_primary' => true]);
    makeAddress($ownerB, ['is_primary' => true]);

    expect(Address::where('is_primary', true)->count())->toBe(2);
});

it('does not auto-promote the first address to primary', function () {
    $owner = makeProfile();
    $address = makeAddress($owner, ['is_primary' => false]);

    expect($address->is_primary)->toBeFalse();
    expect(Address::where('addressable_id', $owner->id)->where('is_primary', true)->count())->toBe(0);
});

it('accepts seeded Address type values through InDynamicEnum', function () {
    seedAddressTypeDynamicEnum();

    $validator = Validator::make(
        ['type' => 'residential'],
        ['type' => [new InDynamicEnum('type', Address::class), 'required']]
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects unknown Address type values through InDynamicEnum', function () {
    seedAddressTypeDynamicEnum();

    $validator = Validator::make(
        ['type' => 'not_a_real_type'],
        ['type' => [new InDynamicEnum('type', Address::class), 'required']]
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('type'))->toBeTrue();
});

it('fails InDynamicEnum when Address type enum is not configured', function () {
    $validator = Validator::make(
        ['type' => 'residential'],
        ['type' => [new InDynamicEnum('type', Address::class)]]
    );

    expect($validator->fails())->toBeTrue();
});

it('accepts Address type through HasAddress validation path when enum is seeded', function () {
    seedAddressTypeDynamicEnum();
    $countryId = DB::table('countries')->insertGetId(['name' => 'Nigeria', 'iso2' => 'NG']);
    $owner = makeProfile();

    $address = $owner->addAddress([
        'country_id' => $countryId,
        'address_line_1' => '15 Admiralty Way',
        'type' => 'residential',
    ], false);

    expect($address)->toBeInstanceOf(Address::class)
        ->and($address->type)->toBe('residential')
        ->and($address->is_primary)->toBeFalse()
        ->and(Schema::hasColumn('addresses', 'school_id'))->toBeFalse()
        ->and($address->getAttributes())->not->toHaveKey('school_id');
});

it('rejects invalid Address type through HasAddress validation path', function () {
    seedAddressTypeDynamicEnum();
    $countryId = DB::table('countries')->insertGetId(['name' => 'Nigeria', 'iso2' => 'NG']);
    $owner = makeProfile();

    expect(fn () => $owner->addAddress([
        'country_id' => $countryId,
        'address_line_1' => '15 Admiralty Way',
        'type' => 'invalid_type',
    ], false))->toThrow(ValidationException::class);
});

it('creates an address via HasAddress without writing school_id', function () {
    seedAddressTypeDynamicEnum();
    $countryId = DB::table('countries')->insertGetId(['name' => 'Nigeria', 'iso2' => 'NG']);
    $owner = makeProfile();

    $address = $owner->addAddress([
        'country_id' => $countryId,
        'address_line_1' => '1 Compatibility Lane',
        'type' => 'office',
    ], true);

    $row = DB::table('addresses')->where('id', $address->id)->first();

    expect($row)->not->toBeNull()
        ->and((bool) $row->is_primary)->toBeTrue()
        ->and($row->addressable_id)->toBe($owner->id)
        ->and(property_exists($row, 'school_id') || isset($row->school_id))->toBeFalse();
});

it('permanently deletes an address with no soft-delete residual', function () {
    $owner = makeProfile();
    $address = makeAddress($owner);
    $id = $address->id;

    $address->delete();

    expect(Address::find($id))->toBeNull();
    expect(DB::table('addresses')->where('id', $id)->exists())->toBeFalse();
});

it('nullOnDelete for location FKs does not destroy the address', function () {
    $owner = makeProfile();
    $countryId = DB::table('countries')->insertGetId(['name' => 'Nigeria', 'iso2' => 'NG']);
    $address = makeAddress($owner, ['country_id' => $countryId]);

    DB::table('countries')->where('id', $countryId)->delete();

    $address->refresh();
    expect($address->country_id)->toBeNull();
    expect(Address::find($address->id))->not->toBeNull();
});
