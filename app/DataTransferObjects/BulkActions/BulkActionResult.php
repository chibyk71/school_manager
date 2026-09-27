<?php

namespace App\DataTransferObjects\BulkActions;

/**
 * Normalized bulk-action result (Phase 6).
 * Supports atomic and partial semantics with optional per-record errors.
 *
 * Backward-compatible helpers (success/failure factories) retained for existing handlers
 * while exposing the richer Phase 6 shape via toArray().
 */
final class BulkActionResult
{
    /**
     * @param  list<array{id?: int|string, message: string, code?: string}>  $errors
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $action,
        public readonly int $processed,
        public readonly int $succeeded,
        public readonly int $failed,
        public readonly int $skipped,
        public readonly bool $success,
        public readonly string $message,
        public readonly array $errors = [],
        public readonly array $meta = [],
    ) {}

    /**
     * Legacy-compatible success factory (maps count → processed/succeeded).
     */
    public static function success(
        string $action,
        int $count,
        string $message,
        array $meta = []
    ): self {
        return new self(
            action: $action,
            processed: $count,
            succeeded: $count,
            failed: 0,
            skipped: 0,
            success: true,
            message: $message,
            errors: [],
            meta: $meta,
        );
    }

    /**
     * Legacy-compatible failure factory.
     */
    public static function failure(
        string $action,
        string $message,
        array $meta = []
    ): self {
        return new self(
            action: $action,
            processed: 0,
            succeeded: 0,
            failed: 0,
            skipped: 0,
            success: false,
            message: $message,
            errors: [],
            meta: $meta,
        );
    }

    /**
     * Phase 6 partial/atomic result factory.
     *
     * @param  list<array{id?: int|string, message: string, code?: string}>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function make(
        string $action,
        int $processed,
        int $succeeded,
        int $failed = 0,
        int $skipped = 0,
        string $message = '',
        array $errors = [],
        array $meta = [],
    ): self {
        $success = $failed === 0 && $succeeded > 0;

        return new self(
            action: $action,
            processed: $processed,
            succeeded: $succeeded,
            failed: $failed,
            skipped: $skipped,
            success: $success,
            message: $message !== '' ? $message : self::defaultMessage($succeeded, $failed, $skipped),
            errors: $errors,
            meta: $meta,
        );
    }

    private static function defaultMessage(int $succeeded, int $failed, int $skipped): string
    {
        $parts = [];
        if ($succeeded > 0) {
            $parts[] = "{$succeeded} succeeded";
        }
        if ($failed > 0) {
            $parts[] = "{$failed} failed";
        }
        if ($skipped > 0) {
            $parts[] = "{$skipped} skipped";
        }

        return $parts === [] ? 'No records processed.' : implode(', ', $parts).'.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            // Phase 6 canonical counts
            'processed' => $this->processed,
            'succeeded' => $this->succeeded,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
            // Legacy fields for unmigrated consumers
            'count' => $this->succeeded,
            'success' => $this->success,
            'message' => $this->message,
            'errors' => $this->errors,
            'meta' => $this->meta,
        ];
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getCount(): int
    {
        return $this->succeeded;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
