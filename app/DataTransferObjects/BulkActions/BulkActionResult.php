<?php

namespace App\DataTransferObjects\BulkActions;

/**
 * BulkActionResult.php
 *
 * Immutable Data Transfer Object (DTO) that standardizes the output of all bulk actions
 * in the Bulk Actions Module.
 *
 * This DTO ensures every bulk operation (delete, restore, force_delete, and future actions)
 * returns a consistent, predictable, and type-safe response structure.
 *
 * Features / Problems Solved:
 * - Provides a single source of truth for bulk action responses
 * - Immutable design prevents accidental mutation after creation
 * - Clear success/failure states with user-friendly messages
 * - Rich metadata support for logging, auditing, and frontend consumption
 * - Easy conversion to array for Inertia responses or JSON API
 * - Improves testability and controller cleanliness
 *
 * Role in the Bulk Actions Package:
 * - Returned by every implementation of BulkActionHandler (DeleteBulkAction, RestoreBulkAction, ForceDeleteBulkAction, etc.)
 * - Used by BulkActionService to wrap handler results
 * - Consumed by controllers to generate consistent redirect messages or Inertia responses
 * - Can be easily extended in the future (e.g., adding `affectedModels`, `duration`, etc.)
 *
 * How it fits into the architecture:
 * 1. BulkActionHandler::handle() returns BulkActionResult
 * 2. BulkActionService catches exceptions and converts them into failure results
 * 3. Controllers receive BulkActionResult and decide how to respond (redirect, Inertia, API)
 * 4. Frontend composables (useDeleteResource, useRestoreResource) can rely on consistent shape
 *
 * Extensibility:
 * New fields can be added to the constructor and toArray() without breaking existing actions.
 */

final class BulkActionResult
{
    /**
     * Create a new successful bulk action result.
     *
     * @param string $action   The action name (e.g. 'delete', 'restore', 'force_delete')
     * @param int $count       Number of records affected
     * @param string $message  User-friendly success message
     * @param array $meta      Additional context (model, force flag, etc.)
     */
    public function __construct(
        public readonly string $action,
        public readonly int $count,
        public readonly bool $success,
        public readonly string $message,
        public readonly array $meta = []
    ) {
    }

    /**
     * Create a success result (recommended factory method).
     */
    public static function success(
        string $action,
        int $count,
        string $message,
        array $meta = []
    ): self {
        return new self(
            action: $action,
            count: $count,
            success: true,
            message: $message,
            meta: $meta
        );
    }

    /**
     * Create a failure result.
     */
    public static function failure(
        string $action,
        string $message,
        array $meta = []
    ): self {
        return new self(
            action: $action,
            count: 0,
            success: false,
            message: $message,
            meta: $meta
        );
    }

    /**
     * Convert the result to an array (useful for Inertia responses or JSON).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'count' => $this->count,
            'success' => $this->success,
            'message' => $this->message,
            'meta' => $this->meta,
        ];
    }

    /**
     * Check if the action was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Get the number of affected records.
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Get the action name.
     */
    public function getAction(): string
    {
        return $this->action;
    }
}
