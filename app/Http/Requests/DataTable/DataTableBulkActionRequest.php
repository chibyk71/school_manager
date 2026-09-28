<?php

namespace App\Http\Requests\DataTable;

use App\Support\DataTable\DataTableSelection;
use App\Support\DataTable\DataTableSelectionNormalizer;
use App\Support\DataTable\DataTableQueryException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Canonical DataTable bulk-action request (Phase 6).
 * Validates structure; resource capability + authorization happen in the controller/service.
 */
class DataTableBulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selection' => ['required', 'array'],
            'selection.type' => ['required', 'string', 'in:ids,query'],
            'selection.ids' => ['required_if:selection.type,ids', 'array'],
            'selection.ids.*' => ['nullable'],
            'selection.query' => ['required_if:selection.type,query', 'array'],
            'action' => ['required', 'string', 'max:100'],
            'payload' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'selection.required' => 'A selection is required.',
            'selection.type.in' => 'Selection type must be "ids" or "query".',
            'action.required' => 'A bulk action identifier is required.',
        ];
    }

    public function selection(): DataTableSelection
    {
        try {
            return DataTableSelectionNormalizer::fromConfig()->fromRequest(
                $this->input('selection', [])
            );
        } catch (DataTableQueryException $e) {
            throw new HttpResponseException(response()->json([
                'message' => $e->getMessage(),
                'errors' => ['selection' => [$e->getMessage()]],
            ], 422));
        }
    }

    public function action(): string
    {
        return (string) $this->input('action');
    }

    public function payload(): mixed
    {
        return $this->input('payload');
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
