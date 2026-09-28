<?php

namespace App\Support\DataTable;

use Illuminate\Http\Request;

/**
 * Dedicated selection normalization path.
 * Does not abuse the ordinary DataTable query normalizer for membership.
 * Rejects page / perPage / sorts on query selections.
 */
final class DataTableSelectionNormalizer
{
    public function __construct(
        private readonly DataTableQueryNormalizer $queryNormalizer = new DataTableQueryNormalizer(),
        private readonly int $maxIds = 5000,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            DataTableQueryNormalizer::fromConfig(),
            (int) config('tables.selection.max_ids', 5000),
        );
    }

    public function maxIds(): int
    {
        return $this->maxIds;
    }

    /**
     * @param  array<string, mixed>|Request  $input
     */
    public function fromRequest(Request|array $input): DataTableSelection
    {
        $data = $input instanceof Request ? $input->input('selection', $input->all()) : $input;

        if (! is_array($data)) {
            throw DataTableQueryException::malformed('Selection must be an object.');
        }

        // Nested under "selection" key when full request body was passed
        if (isset($data['selection']) && is_array($data['selection'])) {
            $data = $data['selection'];
        }

        $type = $data['type'] ?? null;
        if ($type === DataTableSelection::TYPE_IDS) {
            return $this->normalizeIds($data['ids'] ?? null);
        }
        if ($type === DataTableSelection::TYPE_QUERY) {
            return $this->normalizeQuerySelection($data['query'] ?? null);
        }

        throw DataTableQueryException::malformed(
            'Selection type must be "ids" or "query".'
        );
    }

    /**
     * Normalize a membership query (search + filters only).
     *
     * @param  array<string, mixed>|null  $raw
     */
    public function normalizeMembershipQuery(?array $raw): DataTableSelectionQuery
    {
        if ($raw === null) {
            return new DataTableSelectionQuery(null, []);
        }

        if (array_key_exists('page', $raw) || array_key_exists('perPage', $raw) || array_key_exists('per_page', $raw)) {
            throw DataTableQueryException::malformed(
                'Selection query must not include page or perPage.'
            );
        }
        if (array_key_exists('sorts', $raw) || array_key_exists('sort', $raw)) {
            throw DataTableQueryException::malformed(
                'Selection query must not include sorts.'
            );
        }

        $search = $this->queryNormalizer->normalizeSearch($raw['search'] ?? $raw['globalSearch'] ?? null);
        $filters = $this->queryNormalizer->normalizeFilters($raw['filters'] ?? null);

        return new DataTableSelectionQuery($search, $filters);
    }

    /**
     * @param  mixed  $ids
     */
    private function normalizeIds(mixed $ids): DataTableSelection
    {
        if (! is_array($ids)) {
            throw DataTableQueryException::malformed('Selection ids must be an array.');
        }

        if (count($ids) === 0) {
            throw DataTableQueryException::malformed('Selection ids must not be empty.');
        }

        if (count($ids) > $this->maxIds) {
            throw DataTableQueryException::malformed(
                "Explicit ID selection exceeds maximum of {$this->maxIds} IDs."
            );
        }

        return DataTableSelection::ids($ids);
    }

    /**
     * @param  mixed  $query
     */
    private function normalizeQuerySelection(mixed $query): DataTableSelection
    {
        if ($query !== null && ! is_array($query)) {
            throw DataTableQueryException::malformed('Selection query must be an object.');
        }

        return DataTableSelection::query(
            $this->normalizeMembershipQuery(is_array($query) ? $query : null)
        );
    }
}
