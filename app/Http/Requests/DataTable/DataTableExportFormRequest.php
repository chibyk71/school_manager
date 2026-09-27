<?php

namespace App\Http\Requests\DataTable;

use App\Support\DataTable\DataTableExportRequest;
use App\Support\DataTable\DataTableExportTarget;
use App\Support\DataTable\DataTableQuery;
use App\Support\DataTable\DataTableQueryNormalizer;
use App\Support\DataTable\DataTableSelectionNormalizer;
use App\Support\DataTable\DataTableQueryException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Canonical DataTable export form request (Phase 6).
 */
class DataTableExportFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'array'],
            'target' => ['required', 'array'],
            'target.type' => ['required', 'string', 'in:page,ids,query'],
            'target.ids' => ['required_if:target.type,ids', 'array'],
            'target.query' => ['required_if:target.type,query', 'array'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
            'format' => ['nullable', 'string', 'in:csv,xlsx'],
        ];
    }

    public function toExportRequest(): DataTableExportRequest
    {
        try {
            $queryNormalizer = DataTableQueryNormalizer::fromConfig();
            $selectionNormalizer = DataTableSelectionNormalizer::fromConfig();

            $dtQuery = $queryNormalizer->fromArray($this->input('query', []));
            $targetRaw = $this->input('target', []);
            $type = $targetRaw['type'] ?? null;

            $target = match ($type) {
                'page' => DataTableExportTarget::page(),
                'ids' => DataTableExportTarget::ids($targetRaw['ids'] ?? []),
                'query' => DataTableExportTarget::query(
                    $selectionNormalizer->normalizeMembershipQuery($targetRaw['query'] ?? null)
                ),
                default => throw DataTableQueryException::malformed('Invalid export target type.'),
            };

            return new DataTableExportRequest(
                query: $dtQuery,
                target: $target,
                columns: array_values($this->input('columns', [])),
                format: $this->input('format', DataTableExportRequest::FORMAT_CSV),
            );
        } catch (DataTableQueryException $e) {
            throw new HttpResponseException(response()->json([
                'message' => $e->getMessage(),
                'errors' => ['export' => [$e->getMessage()]],
            ], 422));
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
