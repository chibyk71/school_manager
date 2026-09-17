<?php

/**
 * Address Phase 1 — foundational domain tests (schema + model + primary invariant).
 *
 * Focused SQLite schema: only tables required for Address ownership and location FKs.
 * Does not exercise HasAddress redesign, controllers, policies, or Phase 2 lifecycle.
 */

uses(Tests\TestCase::class);

use App\Models\Address;
use App\Models\DynamicEnum;
use App\Models\Profile;
use App\Models\School;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildAddressPhase1Schema();
});

afterEach(function () {
    dropAddressPhase1Schema();
});

function dropAddressPhase1Schema(): void
{
    Schema::dropIfExists('addresses');
    Schema::dropIfExists('cities');
    Schema::dropIfExists('states');
    Schema::dropIfExists('countries');
    Schema::dropIfExists('dynamic_enums');
    Schema::dropIfExists('profiles');
    Schema::dropIfExists('schools');
}

function buildAddressPhase1Schema(): void
{
    dropAddressPhase1Schema();

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('code')->nullable();
        $table->string('slug')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('profiles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    // Minimal nnjeim/world stubs (integer IDs as package uses)
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

    // Run the real Address migration body via Schema (same as production)
    Schema::create('addresses', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuidMorphs('addressable');
        $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
        $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
        $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
        $table->string('address_line_1')->nullable();
        $table->string('address_line_2')->nullable();
        $table->string('landmark')->nullable();
        $table->string('city_text')->nullable();
        $table->string('postal_code')->nullable();
        $table->string('type')->nullable();
        $table->boolean('is_primary')->default(false);
        $table->decimal('latitude', 10, 8)->nullable();
        $table->decimal('longitude', 11, 8)->nullable();
        $table->timestamps();
    });

    // Partial unique index (SQLite)
    DB::statement(
        'CREATE UNIQUE INDEX addresses_one_primary_per_owner ON addresses (addressable_type, addressable_id) WHERE is_primary = 1'
    );
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

// ── Schema ──────────────────────────────────────────────────────────────────

it('has addresses table with expected columns and without school_id tenant_id deleted_at', function () {
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

it('defaults is_primary to false', function () {
    $owner = makeProfile();
    $address = makeAddress($owner, ['is_primary' => null]);
    Address::query()->whereKey($address->id)->delete();

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

// ── Model ───────────────────────────────────────────────────────────────────

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

// ── Ownership ───────────────────────────────────────────────────────────────

it('belongs to addressable owner via polymorphic columns only', function () {
    $owner = makeProfile();
    $address = makeAddress($owner, ['type' => 'office']);

    expect($address->addressable_type)->toBe($owner->getMorphClass())
        ->and($address->addressable_id)->toBe($owner->getKey())
        ->and(Schema::hasColumn('addresses', 'school_id'))->toBeFalse();
});

// ── Primary invariant ───────────────────────────────────────────────────────

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

// ── Dynamic Enum ────────────────────────────────────────────────────────────

it('integrates type with Dynamic Enum seed for Address', function () {
    DynamicEnum::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'type',
        'label' => 'Address Type',
        'applies_to' => Address::class,
        'options' => [
            ['value' => 'residential', 'label' => 'Residential'],
            ['value' => 'school_campus', 'label' => 'School Campus'],
            ['value' => 'office', 'label' => 'Office'],
            ['value' => 'postal', 'label' => 'Postal'],
            ['value' => 'temporary', 'label' => 'Temporary'],
            ['value' => 'billing', 'label' => 'Billing'],
        ],
        'school_id' => null,
    ]);

    $enum = DynamicEnum::query()
        ->where('name', 'type')
        ->where('applies_to', Address::class)
        ->first();

    expect($enum)->not->toBeNull();
    $values = collect($enum->options)->pluck('value')->all();
    expect($values)->toContain('residential', 'school_campus', 'office', 'postal', 'temporary', 'billing');

    $owner = makeProfile();
    $address = makeAddress($owner, ['type' => 'residential']);
    expect($address->type)->toBe('residential');
});

// ── Lifecycle ───────────────────────────────────────────────────────────────

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
