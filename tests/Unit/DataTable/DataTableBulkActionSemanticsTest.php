<?php

use App\Support\DataTable\BulkActionCapability;
use App\Support\DataTable\DataTableBulkActionService;
use App\Services\BulkActions\BulkActionRegistry;

it('treats missing semantics as atomic', function () {
    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $cap = new BulkActionCapability('delete', 'Delete');
    expect($service->isAtomic($cap))->toBeTrue();
});

it('treats explicit atomic semantics as atomic', function () {
    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $cap = new BulkActionCapability('delete', 'Delete', null, true, 'atomic');
    expect($service->isAtomic($cap))->toBeTrue();
});

it('treats partial semantics as non-atomic', function () {
    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $cap = new BulkActionCapability('notify', 'Notify', null, false, 'partial');
    expect($service->isAtomic($cap))->toBeFalse();
});

it('reads semantics from array capability shape', function () {
    $service = new DataTableBulkActionService(new BulkActionRegistry);
    expect($service->isAtomic(['id' => 'x', 'label' => 'X', 'semantics' => 'partial']))->toBeFalse();
    expect($service->isAtomic(['id' => 'y', 'label' => 'Y']))->toBeTrue();
});

it('finds declared capability by id', function () {
    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $caps = [
        new BulkActionCapability('delete', 'Delete', null, true, 'atomic'),
        ['id' => 'restore', 'label' => 'Restore'],
    ];
    expect($service->findCapability('delete', $caps))->not->toBeNull();
    expect($service->findCapability('restore', $caps))->not->toBeNull();
    expect($service->findCapability('explode', $caps))->toBeNull();
    expect($service->isActionDeclared('delete', $caps))->toBeTrue();
    expect($service->isActionDeclared('explode', $caps))->toBeFalse();
});
