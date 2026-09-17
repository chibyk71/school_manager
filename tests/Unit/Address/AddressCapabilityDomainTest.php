<?php

/**
 * Address Phase 2 — HasAddress capability domain tests.
 *
 * Full suite also available in artifacts/AddressDomainTest.phase2.php (Phase1+2 combined).
 */

uses(Tests\TestCase::class);

use App\Models\Address;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildAddressCapPrerequisites();
    runAddressCapMigration();
});

afterEach(function () {
    rollbackAddressCapMigration();
    dropAddressCapPrerequisites();
});

function addressCapMigrationPath(): string
{
    return database_path('migrations/2025_12_01_065635_create_addresses_table.php');
}

function loadAddressCapMigration(): object
{
    return require addressCapMigrationPath();
}

function runAddressCapMigration(): void
{
    Schema::dropIfExists('addresses');
    loadAddressCapMigration()->up();
}

function rollbackAddressCapMigration(): void
{
    if (Schema::hasTable('addresses')) {
        loadAddressCapMigration()->down();
    }
}

function dropAddressCapPrerequisites(): void
{
    Schema::dropIfExists('cities');
    Schema::dropIfExists('states');
    Schema::dropIfExists('countries');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('profiles');
}

function buildAddressCapPrerequisites(): void
{
    dropAddressCapPrerequisites();
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

function seedAddressCapTypeEnum(): void
{
    DB::table('dynamic_enums')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'type',
        'label' => 'Address Type',
        'applies_to' => Address::class,
        'options' => json_encode([
            ['value' => 'residential', 'label' => 'Residential'],
            ['value' => 'office', 'label' => 'Office'],
        ]),
        'school_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeCapProfile(array $attrs = []): Profile
{
    $p = new Profile();
    $p->forceFill(array_merge([
        'id' => (string) Str::uuid(),
        'first_name' => 'Test',
        'last_name' => 'Owner',
    ], $attrs))->save();

    return $p->fresh();
}

function makeCapAddress(Model $owner, array $attrs = []): Address
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

it('creates multiple addresses without auto-primary', function () {
    seedAddressCapTypeEnum();
    $owner = makeCapProfile();
    $a1 = $owner->addAddress(['address_line_1' => 'A1', 'type' => 'residential']);
    $a2 = $owner->addAddress(['address_line_1' => 'A2', 'type' => 'office']);
    expect($a1->is_primary)->toBeFalse()
        ->and($a2->is_primary)->toBeFalse()
        ->and($owner->primaryAddress())->toBeNull()
        ->and($owner->hasAddress())->toBeTrue();
});

it('explicit primary creation clears previous primary', function () {
    seedAddressCapTypeEnum();
    $owner = makeCapProfile();
    $first = $owner->addAddress(['address_line_1' => 'First', 'type' => 'residential'], true);
    $second = $owner->addAddress(['address_line_1' => 'Second', 'type' => 'office'], true);
    expect($second->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse();
});

it('rejects address creation for unsaved owner', function () {
    seedAddressCapTypeEnum();
    $unsaved = new Profile();
    $unsaved->forceFill(['first_name' => 'X', 'last_name' => 'Y']);
    expect(fn () => $unsaved->addAddress(['address_line_1' => 'Orphan', 'type' => 'residential']))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejects cross-owner update and delete', function () {
    seedAddressCapTypeEnum();
    $a = makeCapProfile(['first_name' => 'A']);
    $b = makeCapProfile(['first_name' => 'B']);
    $addr = $a->addAddress(['address_line_1' => 'A', 'type' => 'residential']);
    expect(fn () => $b->updateAddress($addr->id, ['address_line_1' => 'Hacked']))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect(fn () => $b->deleteAddress($addr->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('partial update preserves primary; makePrimary changes it', function () {
    seedAddressCapTypeEnum();
    $owner = makeCapProfile();
    $p = $owner->addAddress(['address_line_1' => 'P', 'type' => 'residential'], true);
    $o = $owner->addAddress(['address_line_1' => 'O', 'type' => 'office']);
    $owner->updateAddress($o->id, ['address_line_2' => 'Suite']);
    expect($o->fresh()->is_primary)->toBeFalse()->and($p->fresh()->is_primary)->toBeTrue();
    $owner->updateAddress($o, ['landmark' => 'Park'], true);
    expect($o->fresh()->is_primary)->toBeTrue()->and($p->fresh()->is_primary)->toBeFalse();
});

it('is_primary in data cannot bypass capability', function () {
    seedAddressCapTypeEnum();
    $owner = makeCapProfile();
    $a = $owner->addAddress(['address_line_1' => 'A', 'type' => 'residential'], true);
    $b = $owner->addAddress(['address_line_1' => 'B', 'type' => 'office', 'is_primary' => true]);
    expect($b->is_primary)->toBeFalse()->and($a->fresh()->is_primary)->toBeTrue();
});

it('set and unset primary; delete primary does not promote', function () {
    seedAddressCapTypeEnum();
    $owner = makeCapProfile();
    $a = $owner->addAddress(['address_line_1' => 'A', 'type' => 'residential'], true);
    $b = $owner->addAddress(['address_line_1' => 'B', 'type' => 'office']);
    $owner->setPrimaryAddress($b);
    expect($b->fresh()->is_primary)->toBeTrue()->and($a->fresh()->is_primary)->toBeFalse();
    $owner->unsetPrimaryAddress();
    expect($owner->primaryAddress())->toBeNull();
    $owner->setPrimaryAddress($a);
    $owner->deleteAddress($a);
    expect($owner->primaryAddress())->toBeNull()->and($b->fresh()->is_primary)->toBeFalse();
});

it('delete is permanent; no soft-delete helpers on capability', function () {
    seedAddressCapTypeEnum();
    $owner = makeCapProfile();
    $addr = $owner->addAddress(['address_line_1' => 'Gone', 'type' => 'residential']);
    $id = $addr->id;
    $owner->deleteAddress($id);
    expect(Address::find($id))->toBeNull()
        ->and(method_exists($owner, 'restoreAllAddresses'))->toBeFalse()
        ->and(method_exists($owner, 'forceDeleteAllAddresses'))->toBeFalse();
});

it('allows create without country_id; requires address_line_1 and type', function () {
    seedAddressCapTypeEnum();
    $owner = makeCapProfile();
    $addr = $owner->addAddress(['address_line_1' => 'Village', 'city_text' => 'Ikeja', 'type' => 'residential']);
    expect($addr->country_id)->toBeNull()->and($addr->city_text)->toBe('Ikeja');
    expect(fn () => $owner->addAddress(['type' => 'residential']))->toThrow(ValidationException::class);
});

it('rejects invalid location hierarchy and coordinate bounds', function () {
    seedAddressCapTypeEnum();
    $c1 = DB::table('countries')->insertGetId(['name' => 'Nigeria', 'iso2' => 'NG']);
    $c2 = DB::table('countries')->insertGetId(['name' => 'Ghana', 'iso2' => 'GH']);
    $state = DB::table('states')->insertGetId(['country_id' => $c2, 'name' => 'Accra']);
    $owner = makeCapProfile();
    expect(fn () => $owner->addAddress([
        'country_id' => $c1, 'state_id' => $state, 'address_line_1' => 'X', 'type' => 'residential',
    ]))->toThrow(ValidationException::class);
    expect(fn () => $owner->addAddress([
        'address_line_1' => 'X', 'type' => 'residential', 'latitude' => 91,
    ]))->toThrow(ValidationException::class);
});

it('deleteAllAddresses only affects current owner', function () {
    seedAddressCapTypeEnum();
    $a = makeCapProfile(['first_name' => 'A']);
    $b = makeCapProfile(['first_name' => 'B']);
    $a->addAddress(['address_line_1' => 'A1', 'type' => 'residential']);
    $bAddr = $b->addAddress(['address_line_1' => 'B1', 'type' => 'residential']);
    $a->deleteAllAddresses();
    expect($a->hasAddress())->toBeFalse()->and(Address::find($bAddr->id))->not->toBeNull();
});

it('soft-deleted owner retains addresses', function () {
    seedAddressCapTypeEnum();
    $owner = makeCapProfile();
    $addr = $owner->addAddress(['address_line_1' => 'Keep', 'type' => 'residential']);
    $owner->delete();
    expect($owner->trashed())->toBeTrue()->and(Address::find($addr->id))->not->toBeNull();
});

it('validation failure on primary create preserves previous primary', function () {
    seedAddressCapTypeEnum();
    $owner = makeCapProfile();
    $existing = $owner->addAddress(['address_line_1' => 'Keep', 'type' => 'residential'], true);
    expect(fn () => $owner->addAddress(['address_line_1' => 'Bad', 'type' => 'not_valid'], true))
        ->toThrow(ValidationException::class);
    expect($owner->primaryAddress()?->id)->toBe($existing->id);
});

it('database uniqueness still rejects two primaries', function () {
    $owner = makeCapProfile();
    makeCapAddress($owner, ['is_primary' => true]);
    expect(fn () => makeCapAddress($owner, ['is_primary' => true]))->toThrow(QueryException::class);
});
