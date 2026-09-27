<?php

namespace App\Support\DataTable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Translates DataTableSelection into an authorized resource record set/query.
 *
 * CRITICAL: The caller MUST pass an already-authorized Builder
 * (tenant / school / section scopes + policy constraints applied).
 * This resolver never becomes an authorization mechanism.
 *
 * For ID selection: authorized query + whereIn(ids)
 * For query selection: authorized query + membership search/filters
 */
final class DataTableSelectionResolver
{
    public function __construct(
        private readonly DataTableQueryEngine $engine = new DataTableQueryEngine(),
    ) {}

    public static function make(?DataTableQueryEngine $engine = null): self
    {
        return new self($engine ?? DataTableQueryEngine::make());
    }

    /**
     * Apply selection onto an authorized base query.
     * Does not execute domain actions — only establishes the record set.
     *
     * @param  Builder  $authorizedQuery  Must already include authorization scopes
     * @param  Model  $model  Model instance for capability metadata
     * @param  array<string>  $extraFields  Optional extra searchable/filterable fields
     */
    public function apply(
        Builder $authorizedQuery,
        Model $model,
        DataTableSelection $selection,
        array $extraFields = [],
    ): Builder {
        if ($selection->isIds()) {
            return $authorizedQuery->whereIn(
                $authorizedQuery->getModel()->getQualifiedKeyName(),
                $selection->ids
            );
        }

        $membership = $selection->query ?? new DataTableSelectionQuery(null, []);

        // Build a DataTableQuery with membership only (no pagination / sorts for membership)
        $dtQuery = new DataTableQuery(
            page: 1,
            perPage: 1,
            search: $membership->search,
            filters: $membership->filters,
            sorts: [],
        );

        return $this->engine->applyQuerySemantics(
            $authorizedQuery,
            $model,
            $dtQuery,
            $extraFields
        );
    }

    /**
     * Resolve selected primary keys (for actions that need ID lists).
     * Still evaluated against the authorized query — IDs outside scope are excluded.
     *
     * @return list<int|string>
     */
    public function resolveIds(
        Builder $authorizedQuery,
        Model $model,
        DataTableSelection $selection,
        array $extraFields = [],
    ): array {
        $query = $this->apply($authorizedQuery, $model, $selection, $extraFields);

        return $query->pluck($query->getModel()->getKeyName())->all();
    }
}
