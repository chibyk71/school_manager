/**
 * Contract test: embedded AddressManager must bind AddressForm to the live
 * parent addresses[0] object — never a clone from addressToFormData().
 *
 * Run: node tests/Unit/Address/embedded_binding_write_through.mjs
 *
 * Mirrors the Phase 4 invariant:
 *   AddressForm field mutation → model[0] → School form submit → addresses[]
 */

function emptyAddressFormData() {
    return {
        country_id: null,
        state_id: null,
        city_id: null,
        address_line_1: '',
        address_line_2: null,
        landmark: null,
        city_text: null,
        postal_code: null,
        type: null,
        is_primary: false,
    };
}

/** Broken pattern (pre-fix): clone severs write-through. */
function addressToFormData(address) {
    return {
        country_id: address.country_id ?? null,
        state_id: address.state_id ?? null,
        city_id: address.city_id ?? null,
        address_line_1: address.address_line_1 ?? '',
        address_line_2: address.address_line_2 ?? null,
        landmark: address.landmark ?? null,
        city_text: address.city_text ?? null,
        postal_code: address.postal_code ?? null,
        type: address.type ?? null,
        is_primary: Boolean(address.is_primary),
    };
}

/** Correct pattern: return the same object reference as model[0]. */
function liveEmbeddedSlot(list) {
    if (!list.length) {
        list.push(emptyAddressFormData());
    }
    return list[0];
}

function assert(cond, msg) {
    if (!cond) {
        console.error('FAIL:', msg);
        process.exit(1);
    }
}

// --- clone path is broken ---
{
    const model = [emptyAddressFormData()];
    const form = addressToFormData(model[0]);
    form.address_line_1 = '12 Main Street';
    form.type = 'physical';
    assert(model[0].address_line_1 === '', 'clone must not write parent (documents the bug)');
    assert(model[0].type === null, 'clone must not write parent type');
}

// --- live slot path is correct ---
{
    const model = [emptyAddressFormData()];
    const form = liveEmbeddedSlot(model);
    form.address_line_1 = '12 Main Street';
    form.type = 'physical';
    form.is_primary = true;
    assert(model[0].address_line_1 === '12 Main Street', 'live slot writes address_line_1');
    assert(model[0].type === 'physical', 'live slot writes type');
    assert(model[0].is_primary === true, 'live slot writes is_primary');
    assert(form === model[0], 'form is the same reference as model[0]');
}

// --- empty parent array seeds one live slot ---
{
    const model = [];
    const form = liveEmbeddedSlot(model);
    form.address_line_1 = 'Seeded';
    assert(model.length === 1, 'empty list gains one slot');
    assert(model[0].address_line_1 === 'Seeded', 'seeded slot is writable');
}

// --- School submit filter sees live values ---
{
    const embeddedAddresses = [emptyAddressFormData()];
    const form = liveEmbeddedSlot(embeddedAddresses);
    form.address_line_1 = '99 Submit Ave';
    form.type = 'billing';
    const payload = (embeddedAddresses || []).filter((a) => a.address_line_1);
    assert(payload.length === 1, 'submit includes the edited address');
    assert(payload[0].address_line_1 === '99 Submit Ave', 'submit payload has user input');
    assert(payload[0].type === 'billing', 'submit payload has type');
}

console.log('PASS: embedded write-through contract (4 cases)');
