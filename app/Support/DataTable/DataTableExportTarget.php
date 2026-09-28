<?php

namespace App\Support\DataTable;

use InvalidArgumentException;

/**
 * Export target model.
 *
 * page  — current page of the live table query (pagination + sorts apply)
 * ids   — exact selected IDs
 * query — all records matching membership (no pagination constraint)
 */
final class DataTableExportTarget
{
    public const TYPE_PAGE = 'page';

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
        if (! in_array($type, [self::TYPE_PAGE, self::TYPE_IDS, self::TYPE_QUERY], true)) {
            throw new InvalidArgumentException("Invalid export target type [{$type}].");
        }
    }

    public static function page(): self
    {
        return new self(self::TYPE_PAGE);
    }

    /**
     * @param  list<int|string>  $ids
     */
    public static function ids(array $ids): self
    {
        return new self(self::TYPE_IDS, DataTableSelection::ids($ids)->ids);
    }

    public static function query(DataTableSelectionQuery $query): self
    {
        return new self(self::TYPE_QUERY, [], $query);
    }

    public function isPage(): bool
    {
        return $this->type === self::TYPE_PAGE;
    }

    public function isIds(): bool
    {
        return $this->type === self::TYPE_IDS;
    }

    public function isQuery(): bool
    {
        return $this->type === self::TYPE_QUERY;
    }

    public function toArray(): array
    {
        return match ($this->type) {
            self::TYPE_PAGE => ['type' => self::TYPE_PAGE],
            self::TYPE_IDS => ['type' => self::TYPE_IDS, 'ids' => $this->ids],
            self::TYPE_QUERY => [
                'type' => self::TYPE_QUERY,
                'query' => $this->query?->toArray() ?? ['search' => null, 'filters' => []],
            ],
            default => ['type' => $this->type],
        };
    }
}
