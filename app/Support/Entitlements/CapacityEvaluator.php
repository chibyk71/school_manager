<?php

namespace App\Support\Entitlements;

use App\Support\Entitlements\Exceptions\InsufficientCapacityException;

/**
 * Shared capacity evaluation against a resolved numeric limit.
 *
 * Does not perform reservation or persistence — File Manager owns
 * reservation and settlement. This helper only compares requested
 * vs available against the entitlement limit.
 */
final class CapacityEvaluator
{
    public function __construct(
        private readonly EntitlementResolver $resolver,
    ) {}

    /**
     * @throws InsufficientCapacityException
     */
    public function assertWithinLimit(
        string $entitlementKey,
        int $requested,
        int $currentUsage,
        int $outstandingReservations = 0,
    ): void {
        $limit = $this->resolver->limit($entitlementKey);
        $accounted = $currentUsage + $outstandingReservations;
        $available = max(0, $limit - $accounted);

        if ($requested > $available) {
            throw new InsufficientCapacityException(
                $entitlementKey,
                $requested,
                $available,
                $limit,
            );
        }
    }

    public function available(
        string $entitlementKey,
        int $currentUsage,
        int $outstandingReservations = 0,
    ): int {
        $limit = $this->resolver->limit($entitlementKey);

        return max(0, $limit - $currentUsage - $outstandingReservations);
    }

    public function limit(string $entitlementKey): int
    {
        return $this->resolver->limit($entitlementKey);
    }
}
