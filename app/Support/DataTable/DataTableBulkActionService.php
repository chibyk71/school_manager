<?php

namespace App\Support\DataTable;

use App\Contracts\BulkActions\AuthorizedQueryBulkAction;
use App\Contracts\BulkActions\PartialBulkAction;
use App\DataTransferObjects\BulkActions\BulkActionResult;
use App\Services\BulkActions\BulkActionRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Phase 6 bulk-action orchestrator for DataTable selection.
 *
 * Canonical execution model:
 *
 *   authorized resource builder
 *       → selection applied
 *       → authorized selection builder
 *       → authorized-query-capable handler
 *
 * Domain business rules remain in handlers / resource layer — not here.
 *
 * Invariants:
 * - $authorizedQuery->getModel()::class must equal $model::class
 * - Handlers must implement AuthorizedQueryBulkAction (no model->newQuery() rebuild)
 * - semantics=partial requires PartialBulkAction; otherwise fail clearly
 * - Atomic (default) wraps execution in DB::transaction
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
        // Builder/model consistency — selection metadata must not target another resource.
        $builderModelClass = $authorizedQuery->getModel()::class;
        $modelClass = $model::class;
        if ($builderModelClass !== $modelClass) {
            return BulkActionResult::failure(
                $action,
                "Authorized query model [{$builderModelClass}] does not match resource model [{$modelClass}].",
                ['builder_model' => $builderModelClass, 'resource_model' => $modelClass]
            );
        }

        $capability = $this->findCapability($action, $capabilities);
        if ($capability === null) {
            return BulkActionResult::failure(
                $action,
                "Action [{$action}] is not available for this resource.",
                ['model' => $modelClass]
            );
        }

        if ($selection->isIds() && $selection->count() === 0) {
            return BulkActionResult::failure($action, 'No records selected for this bulk action.');
        }

        $atomic = $this->isAtomic($capability);

        try {
            $handler = $this->registry->resolve($action);

            if (! $handler->supports($modelClass)) {
                return BulkActionResult::failure(
                    $action,
                    "Action [{$action}] is not supported for this resource.",
                    ['model' => $modelClass]
                );
            }

            if (! $handler instanceof AuthorizedQueryBulkAction) {
                return BulkActionResult::failure(
                    $action,
                    "Action [{$action}] does not support authorized-query execution.",
                    ['model' => $modelClass]
                );
            }

            // Partial capability requires a handler that truly supports per-record semantics.
            if (! $atomic && ! $handler instanceof PartialBulkAction) {
                return BulkActionResult::failure(
                    $action,
                    "Action [{$action}] declares partial semantics but the handler does not support partial execution.",
                    ['model' => $modelClass]
                );
            }

            // Apply selection onto a clone of the authorized builder — remains authoritative.
            $authorizedSelectionQuery = $this->selectionResolver->apply(
                clone $authorizedQuery,
                $model,
                $selection,
                $extraFields
            );

            if (! (clone $authorizedSelectionQuery)->exists()) {
                return BulkActionResult::failure(
                    $action,
                    'No authorized records matched the selection.',
                    ['model' => $modelClass]
                );
            }

            $run = function () use ($handler, $authorizedSelectionQuery, $payload, $action, $modelClass) {
                $result = $handler->handleOnAuthorizedQuery($authorizedSelectionQuery, $payload);
                $this->logSuccess($action, $modelClass, $result->succeeded, $result);

                return $result;
            };

            if ($atomic) {
                return DB::transaction($run);
            }

            // Genuine partial: handler implements PartialBulkAction; no outer transaction.
            return $run();
        } catch (Exception $e) {
            $this->logFailure($action, $modelClass, $selection, $e);

            return BulkActionResult::failure(
                $action,
                $this->userFriendlyMessage($e, $action),
                ['model' => $modelClass]
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

        return $semantics !== 'partial';
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
