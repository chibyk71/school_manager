<?php

use App\Support\DataTable\DataTableCapabilityMap;
use App\Support\DataTable\DataTableOperators;
use App\Support\DataTable\DataTableQuery;
use App\Support\DataTable\DataTableQueryEngine;
use App\Support\DataTable\DataTableQueryException;
use App\Support\DataTable\DataTableQueryNormalizer;

function makeEngineCapabilities(): DataTableCapabilityMap
{
    return DataTableCapabilityMap::fromColumns([
        [
            'field' => 'name',
            'filterable' => true,
            'sortable' => true,
            'searchable' => true,
            'exportable' => true,
            'filterType' => 'text',
            'hidden' => false,
        ],
        [
            'field' => 'email',
            'filterable' => true,
            'sortable' => true,
            'searchable' => true,
            'exportable' => true,
            'filterType' => 'text',
            'hidden' => true,
            'defaultHidden' => true,
        ],
        [
            'field' => 'status',
            'filterable' => true,
            'sortable' => true,
            'searchable' => false,
            'exportable' => true,
            'filterType' => 'dropdown',
        ],
        [
            'field' => 'age',
            'filterable' => true,
            'sortable' => true,
            'searchable' => false,
            'exportable' => true,
            'filterType' => 'number',
        ],
        [
            'field' => 'secret',
            'filterable' => false,
            'sortable' => false,
            'searchable' => false,
            'exportable' => false,
            'filterType' => 'text',
        ],
        [
            'field' => 'user_name',
            'filterable' => true,
            'sortable' => true,
            'searchable' => true,
            'exportable' => true,
            'filterType' => 'text',
            'relation' => 'user',
            'relatedField' => 'name',
        ],
        [
            'field' => 'user.profile.bio',
            'filterable' => true,
            'sortable' => false,
            'searchable' => true,
            'exportable' => true,
            'filterType' => 'text',
            'relation' => 'user.profile',
            'relatedField' => 'bio',
        ],
        [
            'field' => 'active_users_count',
            'filterable' => false,
            'sortable' => false,
            'searchable' => false,
            'exportable' => true,
            'filterType' => 'number',
        ],
        [
            'field' => 'id',
            'filterable' => true,
            'sortable' => true,
            'searchable' => false,
            'exportable' => true,
            'filterType' => 'number',
        ],
    ]);
}

function invokePrivate(object $obj, string $method, array $args = []): mixed
{
    $ref = new ReflectionMethod($obj, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($obj, $args);
}

beforeEach(function () {
    $this->engine = new DataTableQueryEngine(new DataTableQueryNormalizer(50, 100));
    $this->capabilities = makeEngineCapabilities();
});

it('rejects unknown filter fields', function () {
    expect(fn () => invokePrivate($this->engine, 'validateFilters', [
        [['field' => 'missing', 'operator' => 'equals', 'value' => 'x']],
        $this->capabilities,
    ]))->toThrow(DataTableQueryException::class, 'not available for filtering');
});

it('rejects non-filterable fields', function () {
    expect(fn () => invokePrivate($this->engine, 'validateFilters', [
        [['field' => 'secret', 'operator' => 'equals', 'value' => 'x']],
        $this->capabilities,
    ]))->toThrow(DataTableQueryException::class, 'not filterable');
});

it('rejects unknown sort fields', function () {
    expect(fn () => invokePrivate($this->engine, 'validateSorts', [
        [['field' => 'missing', 'direction' => 'asc']],
        $this->capabilities,
    ]))->toThrow(DataTableQueryException::class, 'not available for sorting');
});

it('rejects non-sortable fields including presentation-only aggregates', function () {
    expect(fn () => invokePrivate($this->engine, 'validateSorts', [
        [['field' => 'active_users_count', 'direction' => 'asc']],
        $this->capabilities,
    ]))->toThrow(DataTableQueryException::class, 'not sortable');
});

it('rejects invalid operators at validation', function () {
    expect(fn () => invokePrivate($this->engine, 'validateFilters', [
        [['field' => 'name', 'operator' => 'bogus', 'value' => 'x']],
        $this->capabilities,
    ]))->toThrow(DataTableQueryException::class);
});

it('rejects invalid between arity', function () {
    expect(fn () => invokePrivate($this->engine, 'validateFilters', [
        [['field' => 'age', 'operator' => 'between', 'value' => [1]]],
        $this->capabilities,
    ]))->toThrow(DataTableQueryException::class, 'Invalid value');
});

it('accepts isNull and isNotNull without values', function () {
    invokePrivate($this->engine, 'validateFilters', [
        [
            ['field' => 'name', 'operator' => 'isNull', 'value' => null],
            ['field' => 'email', 'operator' => 'isNotNull', 'value' => null],
        ],
        $this->capabilities,
    ]);
    expect(true)->toBeTrue();
});

it('allows filtering/sorting presentation-hidden columns when capable', function () {
    invokePrivate($this->engine, 'validateFilters', [
        [['field' => 'email', 'operator' => 'contains', 'value' => 'a']],
        $this->capabilities,
    ]);
    invokePrivate($this->engine, 'validateSorts', [
        [['field' => 'email', 'direction' => 'asc']],
        $this->capabilities,
    ]);
    expect($this->capabilities->isFilterable('email'))->toBeTrue();
    expect($this->capabilities->find('email')['hidden'] ?? false)->toBeTrue();
});

it('translates canonical filters to nested Purity params', function () {
    $params = invokePrivate($this->engine, 'toPurityFilterParams', [
        [
            ['field' => 'name', 'operator' => 'contains', 'value' => 'Ada'],
            ['field' => 'status', 'operator' => 'equals', 'value' => 'active'],
            ['field' => 'age', 'operator' => 'between', 'value' => [10, 20]],
        ],
        $this->capabilities,
    ]);

    expect($params['name']['$contains'] ?? null)->toBe('Ada');
    expect($params['status']['$eq'] ?? null)->toBe('active');
    expect($params['age']['$between'] ?? null)->toBe([10, 20]);
});

it('translates isNull and isNotNull to purity nullary operators', function () {
    $params = invokePrivate($this->engine, 'toPurityFilterParams', [
        [
            ['field' => 'name', 'operator' => 'isNull', 'value' => null],
            ['field' => 'email', 'operator' => 'isNotNull', 'value' => null],
        ],
        $this->capabilities,
    ]);
    expect($params['name']['$null'] ?? null)->toBeTrue();
    expect($params['email']['$notNull'] ?? null)->toBeTrue();
});

it('translates relation virtual fields using relation metadata for Purity', function () {
    $params = invokePrivate($this->engine, 'toPurityFilterParams', [
        [['field' => 'user_name', 'operator' => 'contains', 'value' => 'Bob']],
        $this->capabilities,
    ]);
    expect($params['user']['name']['$contains'] ?? null)->toBe('Bob');
});

it('translates nested relation paths for Purity', function () {
    $params = invokePrivate($this->engine, 'toPurityFilterParams', [
        [['field' => 'user.profile.bio', 'operator' => 'contains', 'value' => 'hello']],
        $this->capabilities,
    ]);
    expect($params['user']['profile']['bio']['$contains'] ?? null)->toBe('hello');
});

it('builds purity sort paths for relation aliases', function () {
    $path = invokePrivate($this->engine, 'puritySortPath', ['user_name', $this->capabilities]);
    expect($path)->toBe('user.name');
});

it('covers all canonical operators in toPurity map', function () {
    $map = DataTableOperators::toPurity();
    foreach (DataTableOperators::all() as $op) {
        expect($map)->toHaveKey($op);
    }
});

it('default DataTableQuery perPage is 50', function () {
    $q = new DataTableQuery;
    expect($q->perPage)->toBe(50);
});
