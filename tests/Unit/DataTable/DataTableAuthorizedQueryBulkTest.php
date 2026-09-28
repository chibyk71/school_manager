<?php

/**
 * Regression: bulk execution must not escape non-global authorization on the builder.
 * A where clause that is NOT a model global scope must still constrain the operation.
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
    Schema::dropIfExists('dt_authz_items');
    Schema::create('dt_authz_items', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('department_id');
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

afterEach(function () {
    Schema::dropIfExists('dt_authz_items');
});

class DtAuthzItem extends Model
{
    protected $table = 'dt_authz_items';

    protected $guarded = [];

    use \Illuminate\Database\Eloquent\SoftDeletes;
}

it('cannot delete rows outside a non-global authorized builder scope', function () {
    $inDept = DtAuthzItem::query()->create(['department_id' => 5, 'name' => 'in']);
    $outDept = DtAuthzItem::query()->create(['department_id' => 9, 'name' => 'out']);

    // Authorized builder: department_id = 5 is NOT a model global scope
    $authorized = DtAuthzItem::query()->where('department_id', 5);

    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $caps = [new BulkActionCapability('delete', 'Delete', null, true, 'atomic')];

    $result = $service->execute(
        $authorized,
        new DtAuthzItem,
        DataTableSelection::ids([$inDept->id, $outDept->id]),
        'delete',
        $caps,
    );

    expect($result->success)->toBeTrue()
        ->and(DtAuthzItem::query()->find($inDept->id))->toBeNull()
        ->and(DtAuthzItem::withTrashed()->find($inDept->id))->not->toBeNull()
        // Out-of-scope row must remain active (not soft-deleted)
        ->and(DtAuthzItem::query()->find($outDept->id))->not->toBeNull()
        ->and(DtAuthzItem::query()->find($outDept->id)->department_id)->toBe(9);
});

it('rejects bulk when selection resolves to empty under authorized scope', function () {
    $outDept = DtAuthzItem::query()->create(['department_id' => 9, 'name' => 'out']);

    $authorized = DtAuthzItem::query()->where('department_id', 5);
    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $caps = [new BulkActionCapability('delete', 'Delete', null, true, 'atomic')];

    $result = $service->execute(
        $authorized,
        new DtAuthzItem,
        DataTableSelection::ids([$outDept->id]),
        'delete',
        $caps,
    );

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('No authorized records')
        ->and(DtAuthzItem::query()->find($outDept->id))->not->toBeNull();
});
