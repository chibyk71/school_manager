<?php

use App\Support\DataTable\DataTableExportTarget;
use App\Support\DataTable\DataTableQueryException;
use App\Support\DataTable\DataTableSelectionNormalizer;

it('normalizes id export targets with dedupe via selection normalizer', function () {
    $normalizer = new DataTableSelectionNormalizer(maxIds: 10);
    $selection = $normalizer->fromRequest([
        'type' => 'ids',
        'ids' => [1, 2, 2, 3],
    ]);
    $target = DataTableExportTarget::ids($selection->ids);

    expect($target->isIds())->toBeTrue()
        ->and($target->ids)->toBe([1, 2, 3]);
});

it('rejects over-limit id export targets rather than converting to query', function () {
    $normalizer = new DataTableSelectionNormalizer(maxIds: 3);

    expect(fn () => $normalizer->fromRequest([
        'type' => 'ids',
        'ids' => [1, 2, 3, 4],
    ]))->toThrow(DataTableQueryException::class, 'exceeds maximum');
});

it('rejects empty id export targets', function () {
    $normalizer = new DataTableSelectionNormalizer(maxIds: 10);

    expect(fn () => $normalizer->fromRequest([
        'type' => 'ids',
        'ids' => [],
    ]))->toThrow(DataTableQueryException::class);
});

it('accepts valid id export within limit', function () {
    $normalizer = new DataTableSelectionNormalizer(maxIds: 5);
    $selection = $normalizer->fromRequest([
        'type' => 'ids',
        'ids' => [10, 20],
    ]);
    $target = DataTableExportTarget::ids($selection->ids);

    expect($target->ids)->toBe([10, 20])
        ->and($target->toArray()['type'])->toBe('ids');
});
