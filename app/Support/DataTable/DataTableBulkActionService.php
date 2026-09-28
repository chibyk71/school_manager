<?php

namespace App\Support\DataTable;

use App\DataTransferObjects\BulkActions\BulkActionResult;
use App\Http\Requests\BulkActions\BulkActionRequest;
use App\Services\BulkActions\BulkActionRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Phase 6 bulk-action orchestrator for DataTable selection.
 *
 * Selection → authorization (caller-supplied authorized query) → action dispatch.
 * Domain business rules remain in handlers / resource layer — not here.
 *
 * Semantics:
 * - Declared capability semantics ('atomic' | 'partial') are authoritative.
 * - Atomic (default): outer DB transaction; all-or-nothing for the resolved set.
 * - Partial: no outer transaction; only meaningful when the handler supports per-record
 *   success/failure. Existing registry handlers are atomic-style — partial is opt-in.
 *
 * TOCTOU mitigation:
 * Resolved IDs always come from the authorized builder. Handlers still receive IDs
 * (legacy contract) but the ID set was produced under the authorized scope; global
 * scopes on the model further constrain handler queries. Callers must pass the same
 * authorized builder they use for listing.
 */
final class DataTableBulkActionService
{
    public function __construct(
        private readonly BulkActionRegistry $registry,
        private readonly DataTableSelectionResolver $selectionResolver = new DataTableSelectionResolver(),
    ) {}

    public static function make(?BulkActionRegistry $registry = null): self
    {
        return new self(
            $registry ?? app(BulkActionRegistry::class),
            DataTableSelectionResolver::make(),
        );
    }

    /**
     * Execute a capability-approved bulk action against an authorized resource query.
     *
     * @param  Builder  $authorizedQuery  Tenant/school/section/policy scopes already applied
     * @param  list<BulkActionCapability|array{id: string, label: string, semantics?: string}>  $capabilities
     * @param  array<string, mixed>  $extraFields
     */
    public function execute(
        Builder $authorizedQuery,
        Model $model,
        DataTableSelection $selection,
        string $action,
        array $capabilities,
        mixed $payload = null,
        array $extraFields = [],
    ): BulkActionResult {
        $capability = $this->findCapability($action, $capabilities);
        if ($capability === null) {
            return BulkActionResult::failure(
                $action,
                "Action [{$action}] is not available for this resource.",
                ['model' => $model::class]
            );
        }

        if ($selection->isIds() && $selection->count() === 0) {
            return BulkActionResult::failure($action, 'No records selected for this bulk action.');
        }

        $atomic = $this->isAtomic($capability);

        try {
            // Resolve IDs exclusively through the authorized builder (never trust raw client IDs alone).
            $ids = $this->selectionResolver->resolveIds(
                clone $authorizedQuery,
                $model,
                $selection,
                $extraFields
            );

            if ($ids === []) {
                return BulkActionResult::failure(
                    $action,
                    'No authorized records matched the selection.',
                    ['model' => $model::class]
                );
            }

            $handler = $this->registry->resolve($action);
            $modelClass = $model::class;

            if (! $handler->supports($modelClass)) {
                return BulkActionResult::failure(
                    $action,
                    "Action [{$action}] is not supported for this resource.",
                    ['model' => $modelClass]
                );
            }

            $legacyRequest = $this->makeLegacyRequest($ids, $action, $payload);

            if ($atomic) {
                return DB::transaction(function () use ($handler, $legacyRequest, $modelClass, $action, $ids) {
                    $result = $handler->handle($legacyRequest, $modelClass);
                    $this->logSuccess($action, $modelClass, count($ids), $result);

                    return $result;
                });
            }

            // Partial: no outer transaction. Only use when the handler truly supports
            // per-record partial processing; existing core handlers are atomic-style.
            $result = $handler->handle($legacyRequest, $modelClass);
            $this->logSuccess($action, $modelClass, count($ids), $result);

            return $result;
        } catch (Exception $e) {
            $this->logFailure($action, $model::class, $selection, $e);

            return BulkActionResult::failure(
                $action,
                $this->userFriendlyMessage($e, $action),
                ['model' => $model::class]
            );
        }
    }

    /**
     * @param  list<BulkActionCapability|array{id: string, label: string, semantics?: string}>  $capabilities
     * @return BulkActionCapability|array{id: string, label: string, semantics?: string}|null
     */
    public function findCapability(string $action, array $capabilities): BulkActionCapability|array|null
    {
        foreach ($capabilities as $cap) {
            if ($cap instanceof BulkActionCapability && $cap->id === $action) {
                return $cap;
            }
            if (is_array($cap) && ($cap['id'] ?? null) === $action) {
                return $cap;
            }
        }

        return null;
    }

    /**
     * @param  list<BulkActionCapability|array{id: string, label: string}>  $capabilities
     */
    public function isActionDeclared(string $action, array $capabilities): bool
    {
        return $this->findCapability($action, $capabilities) !== null;
    }

    /**
     * Declared capability semantics are authoritative. Default is atomic.
     *
     * @param  BulkActionCapability|array{semantics?: string}  $capability
     */
    public function isAtomic(BulkActionCapability|array $capability): bool
    {
        $semantics = $capability instanceof BulkActionCapability
            ? $capability->semantics
            : ($capability['semantics'] ?? null);

        // Only 'partial' opts out of the outer transaction.
        return $semantics !== 'partial';
    }

    /**
     * Build a BulkActionRequest that handlers can call getIds()/getAction() on.
     * FormRequest::validated() requires a resolved validator — create() alone is not enough.
     *
     * @param  list<int|string>  $ids
     */
    private function makeLegacyRequest(array $ids, string $action, mixed $payload): BulkActionRequest
    {
        $data = [
            'ids' => array_values(array_map(static fn ($id) => is_numeric($id) ? (int) $id : $id, $ids)),
            'action' => $action,
            'force' => is_array($payload) ? (bool) ($payload['force'] ?? false) : false,
        ];

        $request = BulkActionRequest::create('/', 'POST', $data);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->merge($data);

        // Populate validator so validated() / getIds() work outside an HTTP kernel cycle.
        $validator = \Illuminate\Support\Facades\Validator::make($data, $request->rules());
        $request->setValidator($validator);

        return $request;
    }

    private function logSuccess(string $action, string $modelClass, int $count, BulkActionResult $result): void
    {
        Log::info('DataTable bulk action completed', [
            'action' => $action,
            'model' => $modelClass,
            'count' => $count,
            'succeeded' => $result->succeeded,
            'failed' => $result->failed,
            'user_id' => auth()->id(),
        ]);
    }

    private function logFailure(string $action, string $modelClass, DataTableSelection $selection, Exception $e): void
    {
        Log::error('DataTable bulk action failed', [
            'action' => $action,
            'model' => $modelClass,
            'selection_type' => $selection->type,
            'error' => $e->getMessage(),
            'user_id' => auth()->id(),
        ]);
    }

    private function userFriendlyMessage(Exception $e, string $action): string
    {
        $base = match ($action) {
            'restore' => 'Failed to restore the selected records.',
            'force_delete' => 'Failed to permanently delete the selected records.',
            'delete' => 'Failed to delete the selected records.',
            default => "Failed to execute bulk action [{$action}].",
        };

        return $base.' Please try again or contact support if the problem persists.';
    }
}
