<?php

namespace App\Traits;

use App\Support\DataTable\DataTableQueryEngine;
use App\Support\DataTable\DataTableQueryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * HasTableQuery – thin model entry point for DataTable processing.
 *
 * Authorization, school/tenant/section scoping, and soft-delete modes
 * MUST be applied by the resource controller/query BEFORE this trait runs.
 *
 * School isolation for models using BelongsToSchool remains via SchoolScope global scope.
 * This trait no longer applies school() itself.
 */
trait HasTableQuery
{
    /** @return array<string> */
    public function getHiddenTableColumns(): array
    {
        return property_exists($this, 'hiddenTableColumns') && is_array($this->hiddenTableColumns)
            ? $this->hiddenTableColumns
            : [];
    }

    /** @return array<string> */
    public function getDefaultHiddenColumns(): array
    {
        return property_exists($this, 'defaultHiddenColumns') && is_array($this->defaultHiddenColumns)
            ? $this->defaultHiddenColumns
            : [];
    }

    /**
     * Explicit global-search fields. Empty → capability searchable fields.
     *
     * @return array<string>
     */
    public function getGlobalFilterColumns(): array
    {
        if (! empty($this->globalFilterFields) && is_array($this->globalFilterFields)) {
            return $this->globalFilterFields;
        }

        return [];
    }

    /**
     * @param  Builder  $query  Must already include authorization / school / section scopes
     * @param  array<callable>  $customModifiers
     * @return array{data: mixed, columns: list<array>, meta: array{currentPage: int, perPage: int, total: int, lastPage: int}}
     */
    public function scopeTableQuery(Builder $query, Request $request, array $extraFields = [], array $customModifiers = []): array
    {
        try {
            foreach ($customModifiers as $modifier) {
                if (is_callable($modifier)) {
                    $modifier($query, $request);
                } else {
                    Log::warning('Invalid custom modifier for '.get_class($this));
                }
            }

            return DataTableQueryEngine::make()->process($query, $this, $request, $extraFields);
        } catch (DataTableQueryException $e) {
            Log::notice('DataTable query validation failed', [
                'model' => get_class($this),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Table query failed for model: '.get_class($this), [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            throw $e;
        }
    }
}
