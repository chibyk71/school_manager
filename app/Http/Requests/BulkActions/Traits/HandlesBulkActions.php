<?php

namespace App\Http\Requests\BulkActions\Traits;

use App\Http\Requests\BulkActions\BulkActionRequest;
use App\Services\BulkActions\BulkActionService;
use App\DataTransferObjects\BulkActions\BulkActionResult;
use Illuminate\Http\RedirectResponse;

/**
 * HandlesBulkActions.php
 *
 * Trait that provides clean, standardized helper methods for handling bulk actions
 * in any Resource Controller.
 *
 * This trait dramatically reduces boilerplate in controllers while ensuring consistent
 * behavior, error handling, and user feedback across the entire application.
 *
 * Features / Problems Solved:
 * - Reduces bulk action controller methods to 1–2 lines
 * - Provides consistent success/error redirect responses with flash messages
 * - Works seamlessly with Inertia.js (redirect()->with())
 * - Centralizes response logic so all bulk operations feel the same to the user
 * - Easy to customize per controller if needed (override methods)
 * - Fully integrates with the complete Bulk Actions package (Request + Service + Result)
 *
 * Role in the Bulk Actions Package:
 * - Optional convenience layer for controllers
 * - Uses BulkActionService and BulkActionResult internally
 * - Keeps controllers focused on routing and authorization only
 * - Makes adding bulk actions to new resources (Students, Staff, Departments, etc.) trivial
 *
 * How it fits into the architecture:
 * 1. Controller uses the trait
 * 2. Controller calls $this->handleBulkAction(...)
 * 3. Trait injects request + service → calls service → formats response
 * 4. Returns standardized RedirectResponse with success/error flash message
 *
 * Usage Example:
 *
 * class StudentController extends Controller
 * {
 *     use HandlesBulkActions;
 *
 *     public function destroy(BulkActionRequest $request, BulkActionService $service)
 *     {
 *         return $this->handleBulkAction($request, $service, Student::class);
 *     }
 * }
 */

trait HandlesBulkActions
{
    /**
     * Handle a bulk action with standardized response.
     *
     * @param BulkActionRequest $request
     * @param BulkActionService $service
     * @param string $modelClass Fully qualified model class name
     * @param string|null $redirectRoute Optional custom redirect route (defaults to back())
     * @return RedirectResponse
     */
    protected function handleBulkAction(
        BulkActionRequest $request,
        BulkActionService $service,
        string $modelClass,
        ?string $redirectRoute = null
    ): RedirectResponse {
        $result = $service->execute($request, $modelClass);

        if ($result->isSuccessful()) {
            return $this->successResponse($result, $redirectRoute);
        }

        return $this->errorResponse($result, $redirectRoute);
    }

    /**
     * Handle successful bulk action.
     */
    protected function successResponse(
        BulkActionResult $result,
        ?string $redirectRoute = null
    ): RedirectResponse {
        $response = $redirectRoute
            ? redirect()->route($redirectRoute)
            : redirect()->back();

        return $response->with('success', $result->message);
    }

    /**
     * Handle failed bulk action.
     */
    protected function errorResponse(
        BulkActionResult $result,
        ?string $redirectRoute = null
    ): RedirectResponse {
        $response = $redirectRoute
            ? redirect()->route($redirectRoute)
            : redirect()->back();

        return $response->with('error', $result->message);
    }

    /**
     * Convenience method for delete action.
     */
    protected function handleDelete(
        BulkActionRequest $request,
        BulkActionService $service,
        string $modelClass,
        ?string $redirectRoute = null
    ): RedirectResponse {
        return $this->handleBulkAction($request, $service, $modelClass, $redirectRoute);
    }

    /**
     * Convenience method for restore action.
     */
    protected function handleRestore(
        BulkActionRequest $request,
        BulkActionService $service,
        string $modelClass,
        ?string $redirectRoute = null
    ): RedirectResponse {
        return $this->handleBulkAction($request, $service, $modelClass, $redirectRoute);
    }

    /**
     * Convenience method for force delete action.
     */
    protected function handleForceDelete(
        BulkActionRequest $request,
        BulkActionService $service,
        string $modelClass,
        ?string $redirectRoute = null
    ): RedirectResponse {
        return $this->handleBulkAction($request, $service, $modelClass, $redirectRoute);
    }
}
