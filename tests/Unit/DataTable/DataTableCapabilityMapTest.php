<?php

use App\Support\DataTable\DataTableCapabilityMap;

it('distinguishes capability from hidden presentation', function () {
    $map = DataTableCapabilityMap::fromColumns([
        [
            'field' => 'email',
            'filterable' => true,
            'sortable' => true,
            'searchable' => true,
            'exportable' => true,
            'hidden' => true,
            'filterType' => 'text',
        ],
        [
            'field' => 'secret',
            'filterable' => false,
            'sortable' => false,
            'searchable' => false,
            'exportable' => false,
            'filterType' => 'text',
        ],
    ]);
    expect($map->isFilterable('email'))->toBeTrue();
    expect($map->isSortable('email'))->toBeTrue();
    expect($map->isSearchable('email'))->toBeTrue();
    expect($map->isFilterable('secret'))->toBeFalse();
    expect($map->hasField('missing'))->toBeFalse();
});

it('defaults searchable from text filterable when not explicit', function () {
    $map = DataTableCapabilityMap::fromColumns([
        ['field' => 'name', 'filterable' => true, 'filterType' => 'text'],
        ['field' => 'age', 'filterable' => true, 'filterType' => 'number'],
        ['field' => 'flag', 'filterable' => false, 'filterType' => 'text'],
    ]);
    expect($map->isSearchable('name'))->toBeTrue();
    expect($map->isSearchable('age'))->toBeFalse();
    expect($map->isSearchable('flag'))->toBeFalse();
});

it('lists exportable fields', function () {
    $map = DataTableCapabilityMap::fromColumns([
        ['field' => 'a', 'exportable' => true],
        ['field' => 'b', 'exportable' => false],
        ['field' => 'c'],
    ]);
    expect($map->exportableFields())->toBe(['a', 'c']);
});
