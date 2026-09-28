<?php

/**
 * Bulk execution against an authorized builder:
 * - undeclared actions fail
 * - ID selection outside authorized scope yields no authorized records
 */

use App\Support\DataTable\BulkActionCapability;
use App\Support\DataTable\DataTableBulkActionService;
use App\Support\DataTable\DataTableSelection;
use App\Services\BulkActions\BulkActionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Model::unguard();
    Schema::dropIfExists('dt_bulk_items');
    Schema::create('dt_bulk_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('school_id');
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

afterEach(function () {
    Schema::dropIfExists('dt_bulk_items');
});

class DtBulkItem extends Model
{
    protected $table = 'dt_bulk_items';

    protected $guarded = [];

    use \Illuminate\Database\Eloquent\SoftDeletes;
}

it('rejects undeclared bulk actions without executing', function () {
    $inScope = DtBulkItem::query()->create(['school_id' => 1, 'name' => 'in']);
    $service = new DataTableBulkActionService(new BulkActionRegistry);

    $result = $service->execute(
        DtBulkItem::query()->where('school_id', 1),
        new DtBulkItem,
        DataTableSelection::ids([$inScope->id]),
        'delete_everything',
        [new BulkActionCapability('delete', 'Delete')],
    );

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('not available')
        ->and(DtBulkItem::query()->count())->toBe(1);
});

it('does not act on IDs outside the authorized school builder', function () {
    $inScope = DtBulkItem::query()->create(['school_id' => 1, 'name' => 'in']);
    $outOfScope = DtBulkItem::query()->create(['school_id' => 2, 'name' => 'out']);

    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $capabilities = [new BulkActionCapability('delete', 'Delete', null, true, 'atomic')];

    $result = $service->execute(
        DtBulkItem::query()->where('school_id', 1),
        new DtBulkItem,
        DataTableSelection::ids([$outOfScope->id]),
        'delete',
        $capabilities,
    );

    // No authorized match → failure, out-of-scope row untouched
    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('No authorized records')
        ->and(DtBulkItem::query()->find($outOfScope->id))->not->toBeNull()
        ->and(DtBulkItem::query()->find($inScope->id))->not->toBeNull();
});

it('executes delete only on authorized IDs from a mixed selection', function () {
    $inScope = DtBulkItem::query()->create(['school_id' => 1, 'name' => 'in']);
    $outOfScope = DtBulkItem::query()->create(['school_id' => 2, 'name' => 'out']);

    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $capabilities = [new BulkActionCapability('delete', 'Delete', null, true, 'atomic')];

    $result = $service->execute(
        DtBulkItem::query()->where('school_id', 1),
        new DtBulkItem,
        DataTableSelection::ids([$inScope->id, $outOfScope->id]),
        'delete',
        $capabilities,
    );

    expect($result->success)->toBeTrue()
        ->and(DtBulkItem::query()->find($inScope->id))->toBeNull()
        ->and(DtBulkItem::withTrashed()->find($inScope->id))->not->toBeNull()
        ->and(DtBulkItem::query()->find($outOfScope->id))->not->toBeNull();
});
