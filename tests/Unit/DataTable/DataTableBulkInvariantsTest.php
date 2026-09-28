<?php

/**
 * Phase 6 bulk invariants:
 * - authorized builder model must match resource model
 * - partial capability requires PartialBulkAction handler
 * - atomic remains the default for current handlers
 */

use App\Contracts\BulkActions\AuthorizedQueryBulkAction;
use App\Contracts\BulkActions\BulkActionHandler;
use App\Contracts\BulkActions\PartialBulkAction;
use App\DataTransferObjects\BulkActions\BulkActionResult;
use App\Http\Requests\BulkActions\BulkActionRequest;
use App\Support\DataTable\BulkActionCapability;
use App\Support\DataTable\DataTableBulkActionService;
use App\Support\DataTable\DataTableSelection;
use App\Services\BulkActions\BulkActionRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Model::unguard();
    Schema::dropIfExists('dt_inv_a');
    Schema::dropIfExists('dt_inv_b');
    Schema::create('dt_inv_a', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
    Schema::create('dt_inv_b', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('dt_inv_a');
    Schema::dropIfExists('dt_inv_b');
});

class DtInvA extends Model
{
    protected $table = 'dt_inv_a';

    protected $guarded = [];

    use \Illuminate\Database\Eloquent\SoftDeletes;
}

class DtInvB extends Model
{
    protected $table = 'dt_inv_b';

    protected $guarded = [];
}

/** Minimal partial-capable double for contract demonstration. */
class PartialCapableTestHandler implements BulkActionHandler, AuthorizedQueryBulkAction, PartialBulkAction
{
    public function handle(BulkActionRequest $request, string $modelClass): BulkActionResult
    {
        return BulkActionResult::failure('partial_test', 'Legacy path not used in Phase 6.');
    }

    public function handleOnAuthorizedQuery(Builder $authorizedSelectionQuery, mixed $payload = null): BulkActionResult
    {
        $ids = $authorizedSelectionQuery->pluck($authorizedSelectionQuery->getModel()->getKeyName())->all();
        $succeeded = 0;
        $failed = 0;
        $errors = [];
        foreach ($ids as $id) {
            if ((int) $id % 2 === 0) {
                $succeeded++;
            } else {
                $failed++;
                $errors[] = ['id' => $id, 'message' => 'odd id skipped in test double'];
            }
        }

        return BulkActionResult::make(
            action: 'partial_test',
            processed: count($ids),
            succeeded: $succeeded,
            failed: $failed,
            skipped: 0,
            message: "{$succeeded} succeeded, {$failed} failed.",
            errors: $errors,
        );
    }

    public function getName(): string
    {
        return 'partial_test';
    }

    public function supports(string $modelClass): bool
    {
        return true;
    }
}

it('rejects mismatched authorized builder and resource model', function () {
    $a = DtInvA::query()->create(['name' => 'a']);
    DtInvB::query()->create(['name' => 'b']);

    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $caps = [new BulkActionCapability('delete', 'Delete', null, true, 'atomic')];

    // Builder is DtInvB, resource model is DtInvA
    $result = $service->execute(
        DtInvB::query(),
        new DtInvA,
        DataTableSelection::ids([$a->id]),
        'delete',
        $caps,
    );

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('does not match resource model')
        ->and(DtInvA::query()->find($a->id))->not->toBeNull();
});

it('accepts matching builder and resource model for atomic delete', function () {
    $row = DtInvA::query()->create(['name' => 'ok']);

    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $caps = [new BulkActionCapability('delete', 'Delete', null, true, 'atomic')];

    $result = $service->execute(
        DtInvA::query(),
        new DtInvA,
        DataTableSelection::ids([$row->id]),
        'delete',
        $caps,
    );

    expect($result->success)->toBeTrue()
        ->and(DtInvA::query()->find($row->id))->toBeNull();
});

it('rejects partial capability when handler is not PartialBulkAction', function () {
    $row = DtInvA::query()->create(['name' => 'x']);

    $service = new DataTableBulkActionService(new BulkActionRegistry);
    // delete handler is atomic-style (AuthorizedQueryBulkAction only)
    $caps = [new BulkActionCapability('delete', 'Delete', null, true, 'partial')];

    $result = $service->execute(
        DtInvA::query(),
        new DtInvA,
        DataTableSelection::ids([$row->id]),
        'delete',
        $caps,
    );

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('does not support partial execution')
        ->and(DtInvA::query()->find($row->id))->not->toBeNull();
});

it('allows partial capability when handler implements PartialBulkAction', function () {
    $even = DtInvA::query()->create(['name' => 'even']); // id likely 1 or 2
    $odd = DtInvA::query()->create(['name' => 'odd']);

    $registry = new BulkActionRegistry;
    $registry->register('partial_test', PartialCapableTestHandler::class);

    $service = new DataTableBulkActionService($registry);
    $caps = [new BulkActionCapability('partial_test', 'Partial Test', null, false, 'partial')];

    $result = $service->execute(
        DtInvA::query(),
        new DtInvA,
        DataTableSelection::ids([$even->id, $odd->id]),
        'partial_test',
        $caps,
    );

    expect($result->processed)->toBe(2)
        ->and($result->succeeded + $result->failed)->toBe(2);
});

it('treats missing semantics as atomic for current delete handler', function () {
    $service = new DataTableBulkActionService(new BulkActionRegistry);
    $cap = new BulkActionCapability('delete', 'Delete');
    expect($service->isAtomic($cap))->toBeTrue();
});
