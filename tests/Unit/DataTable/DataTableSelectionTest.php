<?php

use App\Support\DataTable\DataTableSelection;
use App\Support\DataTable\DataTableSelectionNormalizer;
use App\Support\DataTable\DataTableSelectionQuery;
use App\Support\DataTable\DataTableQueryException;

beforeEach(function () {
    $this->normalizer = new DataTableSelectionNormalizer(maxIds: 10);
});

it('builds id selection and deduplicates', function () {
    $sel = DataTableSelection::ids([1, 2, 2, '3', 1]);
    expect($sel->isIds())->toBeTrue()
        ->and($sel->count())->toBe(3)
        ->and($sel->ids)->toBe([1, 2, 3]);
});

it('builds query selection', function () {
    $q = new DataTableSelectionQuery('alice', [
        ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
    ]);
    $sel = DataTableSelection::query($q);
    expect($sel->isQuery())->toBeTrue()
        ->and($sel->query?->search)->toBe('alice')
        ->and($sel->query?->filters)->toHaveCount(1);
});

it('normalizes ids selection from array', function () {
    $sel = $this->normalizer->fromRequest([
        'type' => 'ids',
        'ids' => [5, 6, 5],
    ]);
    expect($sel->isIds())->toBeTrue()
        ->and($sel->ids)->toBe([5, 6]);
});

it('rejects empty ids', function () {
    $this->normalizer->fromRequest(['type' => 'ids', 'ids' => []]);
})->throws(DataTableQueryException::class);

it('rejects oversized id selection without converting to query', function () {
    $ids = range(1, 11);
    $this->normalizer->fromRequest(['type' => 'ids', 'ids' => $ids]);
})->throws(DataTableQueryException::class, 'exceeds maximum');

it('normalizes query selection and strips no page/sorts', function () {
    $sel = $this->normalizer->fromRequest([
        'type' => 'query',
        'query' => [
            'search' => '  bob  ',
            'filters' => [
                'conditions' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
                ],
            ],
        ],
    ]);
    expect($sel->isQuery())->toBeTrue()
        ->and($sel->query?->search)->toBe('bob')
        ->and($sel->query?->filters)->toHaveCount(1);
});

it('rejects query selection that includes page', function () {
    $this->normalizer->fromRequest([
        'type' => 'query',
        'query' => ['page' => 2, 'search' => 'x'],
    ]);
})->throws(DataTableQueryException::class, 'must not include page');

it('rejects query selection that includes sorts', function () {
    $this->normalizer->fromRequest([
        'type' => 'query',
        'query' => [
            'sorts' => [['field' => 'name', 'direction' => 'asc']],
        ],
    ]);
})->throws(DataTableQueryException::class, 'must not include sorts');

it('rejects unknown selection type', function () {
    $this->normalizer->fromRequest(['type' => 'all']);
})->throws(DataTableQueryException::class);

it('serializes selection to array', function () {
    $sel = DataTableSelection::ids([1, 2]);
    expect($sel->toArray())->toBe(['type' => 'ids', 'ids' => [1, 2]]);

    $q = DataTableSelection::query(new DataTableSelectionQuery(null, []));
    expect($q->toArray()['type'])->toBe('query');
});
