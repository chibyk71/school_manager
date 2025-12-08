<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as InertiaResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Response;

/**
 * Base controller for school-scoped operations.
 *
 * Provides shared logic for getting the active school, handling responses,
 * and tenancy scoping. All role-specific controllers (e.g., Guardian, Staff, Student)
 * should extend this to reduce duplication.
 */
abstract class BaseSchoolController extends Controller
{
    /**
     * Get the active school model.
     *
     * @return School
     * @throws \Exception
     */
    protected function getActiveSchool(): School
    {
        $school = GetSchoolModel();
        if (!$school) {
            throw new \Exception('No active school found.');
        }
        return $school;
    }

    /**
     * Get custom fields for a form, scoped to the school and model type.
     *
     * @param int $schoolId
     * @param string $modelType
     * @return \Illuminate\Support\Collection
     */
    protected function getCustomFieldsForForm(int $schoolId, string $modelType)
    {
        return \App\Models\CustomField::where('school_id', $schoolId)
            ->where('model_type', $modelType)
            ->orderBy('sort', 'asc')
            ->get()
            ->map(function ($field) {
                return [
                    'id' => $field->id,
                    'name' => $field->name,
                    'label' => $field->label,
                    'field_type' => $field->field_type,
                    'options' => $field->options,
                    'required' => $field->required,
                    'placeholder' => $field->placeholder,
                    'hint' => $field->hint,
                ];
            });
    }

    /**
     * Standardized success response for web/API.
     *
     * @param Request $request
     * @param string $message
     * @param string|null $redirectRoute
     * @param mixed $data Optional additional data
     * @return RedirectResponse|JsonResponse
     */
    protected function respondWithSuccess(Request $request, string $message, ?string $redirectRoute = null, mixed $data = null): RedirectResponse|JsonResponse
    {
        $response = ['message' => $message];
        if ($data) {
            $response = array_merge($response, is_array($data) ? $data : ['data' => $data]);
        }

        if ($request->wantsJson()) {
            return response()->json($response, 200);
        }

        return $redirectRoute
            ? redirect()->route($redirectRoute)->with('success', $message)
            : redirect()->back()->with('success', $message);
    }

    /**
     * Standardized error response for web/API.
     *
     * @param Request $request
     * @param string $message
     * @param int $statusCode
     * @return RedirectResponse|JsonResponse
     */
    protected function respondWithError(Request $request, string $message, int $statusCode = 400): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['error' => $message], $statusCode);
        }

        return redirect()->back()->with('error', $message)->withInput();
    }
}
