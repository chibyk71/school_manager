<?php

use App\Support\DataTable\BulkActionCapability;
use App\Support\DataTable\DataTableQueryEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Model::unguard();
    Schema::dropIfExists('dt_cap_items');
    Schema::create('dt_cap_items', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('dt_cap_items');
});

class DtCapItem extends Model
{
    protected $table = 'dt_cap_items';

    protected $guarded = [];

    protected $fillable = ['name'];
}

it('exposes capabilities at response root not under meta', function () {
    DtCapItem::query()->create(['name' => 'one']);

    $engine = DataTableQueryEngine::make();
    $extraFields = [
        'name' => [
            'field' => 'name',
            'filterable' => true,
            'sortable' => true,
            'searchable' => true,
            'exportable' => true,
            'filterType' => 'text',
        ],
    ];
    $result = $engine->process(
        DtCapItem::query(),
        new DtCapItem,
        ['page' => 1, 'perPage' => 10],
        $extraFields,
        [new BulkActionCapability('delete', 'Delete', 'pi-trash', true, 'atomic')],
    );

    expect($result)->toHaveKey('capabilities')
        ->and($result['capabilities'])->toHaveKeys(['bulkActions', 'exportable', 'maxSelectionIds'])
        ->and($result['meta'])->not->toHaveKey('capabilities')
        ->and($result['capabilities']['bulkActions'])->toHaveCount(1)
        ->and($result['capabilities']['bulkActions'][0]['id'])->toBe('delete')
        ->and($result['capabilities']['bulkActions'][0]['semantics'])->toBe('atomic')
        ->and($result['capabilities']['maxSelectionIds'])->toBeInt();
});

it('includes empty bulkActions when none provided', function () {
    $engine = DataTableQueryEngine::make();
    $result = $engine->process(
        DtCapItem::query(),
        new DtCapItem,
        ['page' => 1, 'perPage' => 10],
        ['name' => ['field' => 'name', 'exportable' => true, 'filterable' => true, 'sortable' => true, 'filterType' => 'text']],
    );

    expect($result['capabilities']['bulkActions'])->toBe([]);
});
