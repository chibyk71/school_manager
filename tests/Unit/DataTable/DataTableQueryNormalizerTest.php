<?php

use App\Support\DataTable\DataTableOperators;
use App\Support\DataTable\DataTableQueryNormalizer;

beforeEach(function () {
    $this->normalizer = new DataTableQueryNormalizer(defaultPerPage: 50, maxPerPage: 100);
});

it('normalizes page to at least 1', function () {
    expect($this->normalizer->normalizePage(0))->toBe(1);
    expect($this->normalizer->normalizePage(-5))->toBe(1);
    expect($this->normalizer->normalizePage(3))->toBe(3);
});

it('clamps perPage to max and defaults invalid values', function () {
    expect($this->normalizer->normalizePerPage(50))->toBe(50);
    expect($this->normalizer->normalizePerPage(500))->toBe(100);
    expect($this->normalizer->normalizePerPage(0))->toBe(50);
    expect($this->normalizer->normalizePerPage('abc'))->toBe(50);
});

it('treats empty search as null for deterministic keys', function () {
    expect($this->normalizer->normalizeSearch(''))->toBeNull();
    expect($this->normalizer->normalizeSearch('   '))->toBeNull();
    expect($this->normalizer->normalizeSearch(null))->toBeNull();
    expect($this->normalizer->normalizeSearch('  alice  '))->toBe('alice');
});

it('normalizes canonical filter conditions and drops no-ops', function () {
    $filters = $this->normalizer->normalizeFilters([
        'conditions' => [
            ['field' => 'name', 'operator' => 'contains', 'value' => 'John'],
            ['field' => 'status', 'operator' => 'equals', 'value' => ''],
            ['field' => 'age', 'operator' => 'greaterThan', 'value' => 18],
        ],
    ]);
    expect($filters)->toHaveCount(2);
    expect($filters[0]['field'])->toBe('age');
    expect($filters[1]['field'])->toBe('name');
});

it('normalizes legacy Purity filter maps', function () {
    $filters = $this->normalizer->normalizeFilters([
        'name' => ['$contains' => 'Ada'],
        'status' => ['$eq' => 'active'],
    ]);
    expect($filters)->toHaveCount(2);
    $byField = collect($filters)->keyBy('field');
    expect($byField['name']['operator'])->toBe(DataTableOperators::CONTAINS);
    expect($byField['status']['operator'])->toBe(DataTableOperators::EQUALS);
});

it('produces equivalent normalized filters for equivalent semantics', function () {
    $a = $this->normalizer->normalizeFilters([
        'name' => ['$contains' => 'x'],
        'status' => ['$eq' => 'y'],
    ]);
    $b = $this->normalizer->normalizeFilters([
        'conditions' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'y'],
            ['field' => 'name', 'operator' => 'contains', 'value' => 'x'],
        ],
    ]);
    expect($a)->toBe($b);
});

it('normalizes multi-sort and dedupes fields', function () {
    $sorts = $this->normalizer->normalizeSorts([
        ['field' => 'name', 'direction' => 'asc'],
        ['field' => 'created_at', 'order' => -1],
        ['field' => 'name', 'direction' => 'desc'],
    ]);
    expect($sorts)->toHaveCount(2);
    expect($sorts[0])->toBe(['field' => 'name', 'direction' => 'asc']);
    expect($sorts[1])->toBe(['field' => 'created_at', 'direction' => 'desc']);
});

it('accepts isNull without value', function () {
    $filters = $this->normalizer->normalizeFilters([
        'conditions' => [
            ['field' => 'deleted_at', 'operator' => 'isNull', 'value' => null],
        ],
    ]);
    expect($filters)->toHaveCount(1);
    expect($filters[0]['operator'])->toBe(DataTableOperators::IS_NULL);
});

it('rejects unknown operators instead of silently dropping them', function () {
    expect(fn () => $this->normalizer->normalizeFilters([
        'conditions' => [
            ['field' => 'name', 'operator' => 'invalidOperator', 'value' => 'x'],
        ],
    ]))->toThrow(\App\Support\DataTable\DataTableQueryException::class);
});

it('rejects unknown purity-style operator keys', function () {
    expect(fn () => $this->normalizer->normalizeFilters([
        'name' => ['$bogus' => 'x'],
    ]))->toThrow(\App\Support\DataTable\DataTableQueryException::class);
});

it('defaults perPage to 50 when omitted', function () {
    $q = $this->normalizer->fromArray([]);
    expect($q->perPage)->toBe(50);
    expect($q->page)->toBe(1);
});
