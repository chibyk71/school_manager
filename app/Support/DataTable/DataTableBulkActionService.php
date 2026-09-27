<?php

namespace App\Support\DataTable;

use App\Contracts\BulkActions\BulkActionHandler;
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
 * Bridges the existing BulkActionRegistry handlers while accepting
 * DataTableSelection (ids | query) instead of raw ID-only requests.
 */
final class DataTableBulkActionService
{
    public function __construct(
        private readonly BulkActionRegistry $registry,
        private readonly DataTableSelectionResolver $selectionResolver = new DataTableSelectionResolver(),
        private readonly DataTableSelectionNormalizer $selectionNormalizer = new DataTableSelectionNormalizer(),
    ) {}

    public static function make(?BulkActionRegistry $registry = null): self
    {
        return new self(
            $registry ?? app(BulkActionRegistry::class),
            DataTableSelectionResolver::make(),
            DataTableSelectionNormalizer::fromConfig(),
        );
    }

    /**
     * Execute a capability-approved bulk action against an authorized resource query.
     *
     * @param  Builder  $authorizedQuery  Tenant/school/section/policy scopes already applied
     * @param  list<BulkActionCapability>  $capabilities  Resource-declared actions
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
        bool $atomic = true,
    ): BulkActionResult {
        if (! $this->isActionDeclared($action, $capabilities)) {
            return BulkActionResult::failure(
                $action,
                "Action [{$action}] is not available for this resource.",
                ['model' => $model::class]
            );
        }

        if ($selection->isIds() && $selection->count() === 0) {
            return BulkActionResult::failure($action, 'No records selected for this bulk action.');
        }

        try {
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

            // Adapt to legacy BulkActionRequest shape for existing handlers
            $legacyRequest = $this->makeLegacyRequest($ids, $action, $payload);

            if ($atomic) {
                return DB::transaction(function () use ($handler, $legacyRequest, $modelClass, $action, $ids) {
                    $result = $handler->handle($legacyRequest, $modelClass);
                    $this->logSuccess($action, $modelClass, count($ids), $result);

                    return $result;
                });
            }

            // Partial: no outer transaction — handler decides per-record behaviour
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
     * @param  list<BulkActionCapability>  $capabilities
     */
    public function isActionDeclared(string $action, array $capabilities): bool
    {
        foreach ($capabilities as $cap) {
            if ($cap instanceof BulkActionCapability && $cap->id === $action) {
                return true;
            }
            if (is_array($cap) && ($cap['id'] ?? null) === $action) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function makeLegacyRequest(array $ids, string $action, mixed $payload): BulkActionRequest
    {
        $request = BulkActionRequest::create('/', 'POST', [
            'ids' => array_values($ids),
            'action' => $action,
            'force' => is_array($payload) ? (bool) ($payload['force'] ?? false) : false,
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        // Mark as validated so getIds()/getAction() work without full HTTP validation cycle
        $request->merge(['ids' => array_values($ids), 'action' => $action]);

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
