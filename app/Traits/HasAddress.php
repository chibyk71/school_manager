<?php

namespace App\Traits;

use App\Models\Address;
use App\Rules\InDynamicEnum;
use App\Services\DynamicEnum\DynamicEnumValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * HasAddress — canonical reusable backend capability for polymorphic address management.
 *
 * Any Eloquent model that owns addresses (Profile, School, etc.) uses this trait.
 * Address ownership is polymorphic only: Address has no school_id / tenant_id and no
 * independent authorization boundary. The owner supplies tenant/school context.
 *
 * Capability surface:
 *   addresses(), primaryAddress(), hasAddress()
 *   addAddress(), updateAddress(), deleteAddress()
 *   setPrimaryAddress(), unsetPrimaryAddress()
 *   deleteAllAddresses()
 *
 * Rules (Phase 2):
 * - All mutations are owner-scoped (resolve via addresses relationship, never Address::find).
 * - Deletion is permanent (Address has no SoftDeletes; no restore / forceDelete / withTrashed).
 * - is_primary is capability-controlled only ($isPrimary / $makePrimary); never from arbitrary $data.
 * - Zero or one primary per owner; primary mutations are transactional + owner-row locked.
 * - Target resolution for primary mutations happens inside the lock (atomic lifecycle).
 * - Partial-update hierarchy validates against existing + incoming effective location.
 * - First address is not auto-primary; zero primaries are valid; deleting primary does not promote.
 * - Unsaved owners cannot create addresses.
 * - country_id / state_id / city_id are optional; city_text is free-text locality fallback.
 * - type uses Dynamic Enum (InDynamicEnum); do not hardcode allowed values.
 */
trait HasAddress
{
    /**
     * Polymorphic relationship to addresses.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * Address queries for this owner without global scopes that break UPDATE/DELETE
     * (e.g. SchoolScope ROW_NUMBER subqueries on SQLite).
     * Ownership is already constrained by the polymorphic addressable keys.
     */
    protected function addressesForOwner(): MorphMany
    {
        return $this->addresses()->withoutGlobalScopes();
    }

    /**
     * Current primary address, or null when none is set.
     */
    public function primaryAddress(): ?Address
    {
        return $this->addressesForOwner()->where('is_primary', true)->first();
    }

    /**
     * Whether this owner has at least one address.
     */
    public function hasAddress(): bool
    {
        return $this->addressesForOwner()->exists();
    }

    /**
     * Create an address for the current (persisted) owner.
     *
     * First address is not automatically primary. Pass $isPrimary = true to set primary
     * (clears any existing primary inside a transaction with owner-row lock).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws InvalidArgumentException  when the owner is not persisted
     */
    public function addAddress(array $data, bool $isPrimary = false): Address
    {
        $this->assertOwnerIsPersisted();

        $validated = $this->validateAddressData($data, forUpdate: false);
        unset($validated['is_primary']);

        if ($isPrimary) {
            return DB::transaction(function () use ($validated) {
                $this->lockOwnerForPrimaryMutation();
                $this->clearPrimaryFlags();

                return $this->addressesForOwner()->create(array_merge($validated, [
                    'is_primary' => true,
                ]));
            });
        }

        return $this->addressesForOwner()->create(array_merge($validated, [
            'is_primary' => false,
        ]));
    }

    /**
     * Partially update an address owned by this model.
     *
     * Normal updates never change primary status. Pass $makePrimary = true to
     * atomically clear the current primary and promote the target.
     *
     * Primary-changing updates resolve the target inside the owner-locked transaction.
     * Hierarchy validation uses the effective location (existing attributes + incoming).
     *
     * @param  Address|string  $address  Address instance or key belonging to this owner
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateAddress(Address|string $address, array $data, bool $makePrimary = false): Address
    {
        $key = $address instanceof Address ? $address->getKey() : $address;

        // Resolve once for hierarchy merge (validation). Primary path re-resolves under lock.
        $existing = $this->resolveOwnedAddress($key);
        $validated = $this->validateAddressData($data, forUpdate: true, existing: $existing);
        unset($validated['is_primary']);

        if ($makePrimary) {
            return DB::transaction(function () use ($key, $validated) {
                $this->lockOwnerForPrimaryMutation();
                $target = $this->resolveOwnedAddress($key);
                $this->clearPrimaryFlags();
                $validated['is_primary'] = true;
                $target->update($validated);

                return $target->fresh();
            });
        }

        $existing->update($validated);

        return $existing->fresh();
    }

    /**
     * Permanently delete an address owned by this model.
     *
     * Deleting a primary leaves zero primaries (no automatic promotion).
     * Deleting a non-primary leaves the primary unchanged.
     *
     * @param  Address|string  $address
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function deleteAddress(Address|string $address): bool
    {
        $target = $this->resolveOwnedAddress($address);

        return (bool) $target->delete();
    }

    /**
     * Make the given owned address the sole primary (atomic).
     *
     * Target is resolved inside the transaction after the owner-row lock so the
     * entire primary mutation is a single serialized unit of work.
     *
     * @param  Address|string  $address
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function setPrimaryAddress(Address|string $address): Address
    {
        $key = $address instanceof Address ? $address->getKey() : $address;

        return DB::transaction(function () use ($key) {
            $this->lockOwnerForPrimaryMutation();
            $target = $this->resolveOwnedAddress($key);
            $this->clearPrimaryFlags();
            $target->update(['is_primary' => true]);

            return $target->fresh();
        });
    }

    /**
     * Clear primary status for all addresses of this owner.
     * Results in zero primaries; never promotes another address.
     */
    public function unsetPrimaryAddress(): void
    {
        DB::transaction(function () {
            $this->lockOwnerForPrimaryMutation();
            $this->clearPrimaryFlags();
        });
    }

    /**
     * Permanently delete every address belonging to this owner.
     * Intended for permanent owner deletion cleanup (polymorphic morph cannot cascade via FK).
     */
    public function deleteAllAddresses(): void
    {
        $this->addressesForOwner()->delete();
    }

    /**
     * Resolve an Address instance or key through this owner's relationship only.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function resolveOwnedAddress(Address|string $address): Address
    {
        $key = $address instanceof Address ? $address->getKey() : $address;

        return $this->addressesForOwner()->whereKey($key)->firstOrFail();
    }

    /**
     * Serialize primary mutations on the owner row so concurrent changes cannot
     * produce two primaries for the same owner. Different owners do not block each other.
     */
    protected function lockOwnerForPrimaryMutation(): void
    {
        if (! $this->exists || $this->getKey() === null) {
            return;
        }

        // Re-query the owner row under FOR UPDATE. Avoids relying on $this being
        // the same connection instance inside nested transactions.
        $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->first();
    }

    /**
     * Clear is_primary on all of this owner's addresses (no promotion).
     */
    protected function clearPrimaryFlags(): void
    {
        $this->addressesForOwner()->where('is_primary', true)->update(['is_primary' => false]);
    }

    /**
     * Unsaved models must not create orphaned address rows.
     *
     * @throws InvalidArgumentException
     */
    protected function assertOwnerIsPersisted(): void
    {
        if (! $this->exists || $this->getKey() === null) {
            throw new InvalidArgumentException(
                'Cannot create an address for an unsaved '.static::class.' instance. Persist the owner first.'
            );
        }
    }

    /**
     * Validate address payload against Phase 1 schema / domain rules.
     *
     * Create: address_line_1 and type required; country/state/city optional.
     * Update: all fields sometimes (partial). Hierarchy is checked against the
     * effective location (existing address attributes merged with incoming $data).
     * Only the requested fields from $data are returned for persistence.
     * is_primary is never accepted from $data — stripped by callers.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateAddressData(array $data, bool $forUpdate = false, ?Address $existing = null): array
    {
        // Canonical scalar storage for Dynamic Enum fields
        if (isset($data['type']) && is_string($data['type'])) {
            $data['type'] = DynamicEnumValue::canonicalize($data['type']);
        }

        $presence = $forUpdate ? 'sometimes' : 'required';

        // Effective location for hierarchy: existing attrs + incoming overrides.
        $effective = $data;
        if ($existing !== null) {
            $effective = array_merge(
                [
                    'country_id' => $existing->country_id,
                    'state_id' => $existing->state_id,
                    'city_id' => $existing->city_id,
                ],
                $data
            );
        }

        $countryId = $effective['country_id'] ?? null;
        $stateId = $effective['state_id'] ?? null;

        $rules = [
            // Location FKs optional; hierarchical when present (against effective parent)
            'country_id' => ['nullable', 'exists:countries,id'],
            'state_id' => [
                'nullable',
                $countryId !== null && $countryId !== ''
                    ? 'exists:states,id,country_id,'.$countryId
                    : 'exists:states,id',
            ],
            'city_id' => [
                'nullable',
                $stateId !== null && $stateId !== ''
                    ? 'exists:cities,id,state_id,'.$stateId
                    : 'exists:cities,id',
            ],

            // Core fields
            'address_line_1' => [$presence, 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'city_text' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],

            // Dynamic Enum — authoritative for type
            'type' => [$presence, 'string', new InDynamicEnum('address.type', function_exists('GetSchoolModel') ? GetSchoolModel() : null)],

            // Coordinates
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];

        // Reject explicit is_primary in payload (capability-controlled only)
        if (array_key_exists('is_primary', $data)) {
            unset($data['is_primary']);
        }

        // Validate the requested fields, but hierarchy rules use effective parents above.
        // For fields present only on existing (not in $data), "sometimes" skips them —
        // so we additionally assert effective hierarchy consistency when location changes.
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // When the payload changes a parent without supplying the child, the child may
        // still sit on the row from a previous country/state. Reject that inconsistency.
        if ($forUpdate && $existing !== null) {
            $this->assertEffectiveLocationHierarchy($effective);
        }

        return $validated;
    }

    /**
     * Ensure the effective (existing + incoming) country → state → city chain is coherent.
     *
     * @param  array<string, mixed>  $effective
     *
     * @throws ValidationException
     */
    protected function assertEffectiveLocationHierarchy(array $effective): void
    {
        $countryId = $effective['country_id'] ?? null;
        $stateId = $effective['state_id'] ?? null;
        $cityId = $effective['city_id'] ?? null;

        $errors = [];

        if ($stateId !== null && $stateId !== '' && $countryId !== null && $countryId !== '') {
            $stateOk = DB::table('states')
                ->where('id', $stateId)
                ->where('country_id', $countryId)
                ->exists();
            if (! $stateOk) {
                $errors['state_id'] = ['The selected state does not belong to the selected country.'];
            }
        }

        if ($cityId !== null && $cityId !== '' && $stateId !== null && $stateId !== '') {
            $cityOk = DB::table('cities')
                ->where('id', $cityId)
                ->where('state_id', $stateId)
                ->exists();
            if (! $cityOk) {
                $errors['city_id'] = ['The selected city does not belong to the selected state.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
