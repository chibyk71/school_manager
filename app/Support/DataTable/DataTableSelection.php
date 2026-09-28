<?php

namespace App\Support\DataTable;

use InvalidArgumentException;

/**
 * Canonical selection model.
 *
 * type=ids  → exact record identifiers (cross-page, not a snapshot of a query)
 * type=query → membership evaluated at execution time against authorized resource query
 */
final class DataTableSelection
{
    public const TYPE_IDS = 'ids';

    public const TYPE_QUERY = 'query';

    /**
     * @param  list<int|string>  $ids
     */
    private function __construct(
        public readonly string $type,
        public readonly array $ids = [],
        public readonly ?DataTableSelectionQuery $query = null,
    ) {
        if ($type !== self::TYPE_IDS && $type !== self::TYPE_QUERY) {
            throw new InvalidArgumentException("Invalid selection type [{$type}].");
        }
        if ($type === self::TYPE_IDS && $query !== null) {
            throw new InvalidArgumentException('ID selection must not carry a membership query.');
        }
        if ($type === self::TYPE_QUERY && $query === null) {
            throw new InvalidArgumentException('Query selection requires a membership query.');
        }
    }

    /**
     * @param  list<int|string>  $ids
     */
    public static function ids(array $ids): self
    {
        $normalized = [];
        $seen = [];
        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && $id !== '')) {
                $key = (string) $id;
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $normalized[] = is_numeric($id) && (string) (int) $id === (string) $id
                        ? (int) $id
                        : $id;
                }
            }
        }

        return new self(self::TYPE_IDS, $normalized, null);
    }

    public static function query(DataTableSelectionQuery $query): self
    {
        return new self(self::TYPE_QUERY, [], $query);
    }

    public function isIds(): bool
    {
        return $this->type === self::TYPE_IDS;
    }

    public function isQuery(): bool
    {
        return $this->type === self::TYPE_QUERY;
    }

    public function count(): int
    {
        return $this->isIds() ? count($this->ids) : 0;
    }

    public function toArray(): array
    {
        if ($this->isIds()) {
            return [
                'type' => self::TYPE_IDS,
                'ids' => $this->ids,
            ];
        }

        return [
            'type' => self::TYPE_QUERY,
            'query' => $this->query?->toArray() ?? ['search' => null, 'filters' => []],
        ];
    }
}
