<?php

namespace App\Contracts\Academic;

/**
 * Opt-in contract: this resource declares an Academic Session/Term dependency
 * that the Academic module must know about.
 *
 * Academic does not inspect the resource's business state — only that the
 * dependency exists (for session/term date and deletion protection).
 *
 * Implement via the TracksAcademicUsage trait (or equivalent) so lifecycle
 * events keep academic_period_usages in sync.
 */
interface TracksAcademicUsage
{
    /**
     * School that owns this resource (must match session/term school).
     */
    public function academicUsageSchoolId(): ?string;

    /**
     * Academic session this resource depends on (required when trackable).
     */
    public function academicUsageSessionId(): ?string;

    /**
     * Optional term this resource depends on. Null = session-level only.
     * When set, the term must belong to academicUsageSessionId().
     */
    public function academicUsageTermId(): ?string;

    /**
     * Whether this instance should currently be registered.
     * Typically false when soft-deleted or when session id is missing.
     */
    public function shouldTrackAcademicUsage(): bool;
}
