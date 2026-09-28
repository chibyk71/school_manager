<?php

use App\Support\DataTable\DataTableCapabilityMap;
use App\Support\DataTable\DataTableExportRequest;
use App\Support\DataTable\DataTableExportService;
use App\Support\DataTable\DataTableExportTarget;
use App\Support\DataTable\DataTableQuery;
use App\Support\DataTable\DataTableQueryException;
use App\Support\DataTable\DataTableSelectionQuery;

/**
 * Column authorization unit tests (no DB required).
 * Full export integration tests require a model + authorized query.
 */

it('export target page has no ids or query', function () {
    $t = DataTableExportTarget::page();
    expect($t->isPage())->toBeTrue()
        ->and($t->toArray())->toBe(['type' => 'page']);
});

it('export target ids carries ids', function () {
    $t = DataTableExportTarget::ids([1, 2, 2]);
    expect($t->isIds())->toBeTrue()
        ->and($t->ids)->toBe([1, 2]);
});

it('export target query carries membership', function () {
    $t = DataTableExportTarget::query(new DataTableSelectionQuery('x', []));
    expect($t->isQuery())->toBeTrue()
        ->and($t->query?->search)->toBe('x');
});

it('export request serializes cleanly', function () {
    $req = new DataTableExportRequest(
        query: new DataTableQuery(1, 50, 'search', [], []),
        target: DataTableExportTarget::page(),
        columns: ['name', 'email'],
        format: DataTableExportRequest::FORMAT_CSV,
    );
    $arr = $req->toArray();
    expect($arr['columns'])->toBe(['name', 'email'])
        ->and($arr['format'])->toBe('csv')
        ->and($arr['target']['type'])->toBe('page');
});

it('capability map distinguishes exportable from non-exportable', function () {
    $map = DataTableCapabilityMap::fromColumns([
        ['field' => 'name', 'exportable' => true],
        ['field' => 'secret', 'exportable' => false],
        ['field' => 'hidden_ok', 'hidden' => true, 'exportable' => true],
    ]);
    expect($map->isExportable('name'))->toBeTrue()
        ->and($map->isExportable('secret'))->toBeFalse()
        ->and($map->isExportable('hidden_ok'))->toBeTrue()
        ->and($map->exportableFields())->toBe(['name', 'hidden_ok']);
});
