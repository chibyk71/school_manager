<?php

namespace App\Support\DataTable;

use Illuminate\Http\Request;

/**
 * Deterministic normalization of DataTable query input.
 * Equivalent semantic queries MUST produce equivalent normalized representations.
 */
final class DataTableQueryNormalizer
{
    public function __construct(
        private readonly int $defaultPerPage = 50,
        private readonly int $maxPerPage = 100,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            defaultPerPage: (int) config('tables.default_per_page', 50),
            maxPerPage: (int) config('tables.max_per_page', 100),
        );
    }

    public function fromRequest(Request $request): DataTableQuery
    {
        return new DataTableQuery(
            page: $this->normalizePage($request->input('page', 1)),
            perPage: $this->normalizePerPage($request->input('perPage', $request->input('per_page', $this->defaultPerPage))),
            search: $this->normalizeSearch($request->input('search', $request->input('globalSearch'))),
            filters: $this->normalizeFilters($request->input('filters')),
            sorts: $this->normalizeSorts($request->input('sorts', $request->input('sort'))),
        );
    }

    /** @param array<string, mixed> $input */
    public function fromArray(array $input): DataTableQuery
    {
        return new DataTableQuery(
            page: $this->normalizePage($input['page'] ?? 1),
            perPage: $this->normalizePerPage($input['perPage'] ?? $input['per_page'] ?? $this->defaultPerPage),
            search: $this->normalizeSearch($input['search'] ?? $input['globalSearch'] ?? null),
            filters: $this->normalizeFilters($input['filters'] ?? null),
            sorts: $this->normalizeSorts($input['sorts'] ?? $input['sort'] ?? null),
        );
    }

    public function normalizePage(mixed $page): int
    {
        return max(1, (int) $page);
    }

    public function normalizePerPage(mixed $perPage): int
    {
        if (! is_numeric($perPage)) {
            return $this->defaultPerPage;
        }
        $n = (int) $perPage;
        if ($n < 1) {
            return $this->defaultPerPage;
        }

        return min($n, $this->maxPerPage);
    }

    public function normalizeSearch(mixed $search): ?string
    {
        if ($search === null || $search === '') {
            return null;
        }
        if (! is_string($search) && ! is_numeric($search)) {
            return null;
        }
        $trimmed = trim(strip_tags((string) $search));

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<array{field: string, operator: string, value: mixed}>
     */
    public function normalizeFilters(mixed $filters): array
    {
        if ($filters === null || $filters === '' || $filters === []) {
            return [];
        }
        if (is_string($filters)) {
            $decoded = json_decode($filters, true);
            if (! is_array($decoded)) {
                return [];
            }
            $filters = $decoded;
        }
        if (! is_array($filters)) {
            return [];
        }

        if (isset($filters['conditions']) && is_array($filters['conditions'])) {
            return $this->normalizeConditionList($filters['conditions']);
        }
        if (array_is_list($filters)) {
            return $this->normalizeConditionList($filters);
        }

        $result = [];
        foreach ($filters as $field => $spec) {
            if (! is_string($field) || $field === '' || $field === 'global') {
                continue;
            }
            $normalized = $this->normalizeFilterSpec($field, $spec);
            if ($normalized !== null) {
                $result[] = $normalized;
            }
        }
        usort($result, fn ($a, $b) => [$a['field'], $a['operator']] <=> [$b['field'], $b['operator']]);

        return $result;
    }

    /** @param list<mixed> $conditions @return list<array{field: string, operator: string, value: mixed}> */
    private function normalizeConditionList(array $conditions): array
    {
        $result = [];
        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }
            $field = $condition['field'] ?? null;
            if (! is_string($field) || $field === '') {
                continue;
            }
            $rawOperator = $condition['operator'] ?? $condition['matchMode'] ?? 'equals';
            $operator = $this->resolveOperator($rawOperator);
            if ($operator === null) {
                throw DataTableQueryException::malformed('Filter operator is required for field ['.$field.'].');
            }
            $value = $condition['value'] ?? null;
            if ($this->isNoOpFilter($operator, $value)) {
                continue;
            }
            $result[] = [
                'field' => $field,
                'operator' => $operator,
                'value' => $this->normalizeFilterValue($operator, $value),
            ];
        }
        usort($result, fn ($a, $b) => [$a['field'], $a['operator']] <=> [$b['field'], $b['operator']]);

        return $result;
    }

    /** @return array{field: string, operator: string, value: mixed}|null */
    private function normalizeFilterSpec(string $field, mixed $spec): ?array
    {
        if ($spec === null || $spec === '' || $spec === []) {
            return null;
        }
        if (is_array($spec) && array_key_exists('value', $spec) && (isset($spec['matchMode']) || isset($spec['operator']))) {
            $operator = $this->resolveOperator($spec['matchMode'] ?? $spec['operator'] ?? 'equals');
            if ($operator === null) {
                throw DataTableQueryException::malformed('Filter operator is required for field ['.$field.'].');
            }
            if ($this->isNoOpFilter($operator, $spec['value'])) {
                return null;
            }

            return ['field' => $field, 'operator' => $operator, 'value' => $this->normalizeFilterValue($operator, $spec['value'])];
        }
        if (is_array($spec) && ! array_is_list($spec)) {
            $sawOperator = false;
            foreach ($spec as $opKey => $value) {
                $operator = $this->resolveOperator((string) $opKey);
                $sawOperator = true;
                if ($operator === null || $this->isNoOpFilter($operator, $value)) {
                    continue;
                }

                return ['field' => $field, 'operator' => $operator, 'value' => $this->normalizeFilterValue($operator, $value)];
            }
            if ($sawOperator) {
                return null;
            }

            return null;
        }
        if ($this->isNoOpFilter(DataTableOperators::EQUALS, $spec)) {
            return null;
        }

        return ['field' => $field, 'operator' => DataTableOperators::EQUALS, 'value' => $this->normalizeFilterValue(DataTableOperators::EQUALS, $spec)];
    }

    /**
     * Resolve a raw operator token to a canonical operator.
     * Unknown non-empty operators raise DataTableQueryException (never silently dropped).
     */
    private function resolveOperator(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $map = DataTableOperators::fromMatchMode();
        if (isset($map[$raw])) {
            return $map[$raw];
        }
        if (DataTableOperators::isValid($raw)) {
            return $raw;
        }

        throw DataTableQueryException::unsupportedOperator('*', $raw);
    }

    private function isNoOpFilter(string $operator, mixed $value): bool
    {
        if (DataTableOperators::isNullary($operator)) {
            return false;
        }
        if ($value === null || $value === '') {
            return true;
        }

        return is_array($value) && $value === [];
    }

    private function normalizeFilterValue(string $operator, mixed $value): mixed
    {
        if (DataTableOperators::isNullary($operator)) {
            return null;
        }
        if (DataTableOperators::expectsArray($operator)) {
            return is_array($value) ? array_values($value) : [$value];
        }
        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    /**
     * @return list<array{field: string, direction: 'asc'|'desc'}>
     */
    public function normalizeSorts(mixed $sorts): array
    {
        if ($sorts === null || $sorts === '' || $sorts === []) {
            return [];
        }
        if (is_string($sorts)) {
            if (str_starts_with($sorts, '[') || str_starts_with($sorts, '{')) {
                $decoded = json_decode($sorts, true);
                if (is_array($decoded)) {
                    return $this->normalizeSorts($decoded);
                }
            }

            return $this->parseSortString($sorts);
        }
        if (! is_array($sorts)) {
            return [];
        }
        $result = [];
        $seen = [];
        foreach ($sorts as $item) {
            if (is_string($item)) {
                foreach ($this->parseSortString($item) as $parsed) {
                    if (isset($seen[$parsed['field']])) {
                        continue;
                    }
                    $seen[$parsed['field']] = true;
                    $result[] = $parsed;
                }
                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $field = $item['field'] ?? null;
            if (! is_string($field) || $field === '' || isset($seen[$field])) {
                continue;
            }
            $direction = $this->resolveDirection($item['direction'] ?? $item['order'] ?? 'asc');
            if ($direction === null) {
                continue;
            }
            $seen[$field] = true;
            $result[] = ['field' => $field, 'direction' => $direction];
        }

        return $result;
    }

    /** @return list<array{field: string, direction: 'asc'|'desc'}> */
    private function parseSortString(string $raw): array
    {
        $result = [];
        foreach (array_filter(array_map('trim', explode(',', $raw))) as $part) {
            if ($part === '') {
                continue;
            }
            if (str_contains($part, ':')) {
                [$field, $dir] = array_pad(explode(':', $part, 2), 2, 'asc');
            } else {
                $field = $part;
                $dir = 'asc';
            }
            $field = trim($field);
            if ($field === '') {
                continue;
            }
            $result[] = ['field' => $field, 'direction' => $this->resolveDirection($dir) ?? 'asc'];
        }

        return $result;
    }

    private function resolveDirection(mixed $raw): ?string
    {
        if ($raw === 1 || $raw === '1' || $raw === 'asc' || $raw === 'ASC') {
            return 'asc';
        }
        if ($raw === -1 || $raw === '-1' || $raw === 'desc' || $raw === 'DESC') {
            return 'desc';
        }

        return null;
    }
}
