<?php

namespace App\Support\Auth;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Canonical helpers for treating a Person primary email as an authentication identifier.
 *
 * Domain rules:
 * - profiles.email is the Person's primary personal email (not stored on users).
 * - That email is usable for authentication/recovery only when the linked Authentication
 *   Account has auth_email_verified_at set and auth_email_enabled true.
 * - Soft-deleted Persons are never eligible.
 * - Shared emails that match more than one eligible Person/Account fail closed (null).
 *
 * All recovery and login-by-email paths must normalize addresses through normalize()
 * so lookup, OTP generation, notification destination, and validation use one value.
 */
final class AuthenticationEmail
{
    /**
     * Produce the canonical email string used for identity and OTP operations.
     *
     * @param  string|null  $email  Raw user input; null or blank becomes an empty string.
     * @return string  Lower-case, trimmed value. Empty string means no usable address.
     *
     * Why: Mixed-case input must not create divergent OTP identifiers or lookup misses.
     * Callers must use this result for every subsequent step in the same request.
     */
    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * Profiles linked to an Authentication Account that may use this email for auth.
     *
     * @param  string  $normalizedEmail  Value from normalize(); case-folded already.
     * @return Collection<int, Profile>  Zero or more eligible profiles (with user loaded).
     *
     * Eligibility filter:
     * - Profile row not soft-deleted.
     * - user_id present and related User exists.
     * - User.auth_email_verified_at is non-null.
     * - User.auth_email_enabled is true.
     *
     * Does not enforce uniqueness; callers that require a single account must use
     * resolveEligibleAccount().
     */
    public static function eligibleProfilesFor(string $normalizedEmail): Collection
    {
        if ($normalizedEmail === '') {
            return collect();
        }

        return Profile::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->whereNotNull('user_id')
            ->whereNull('deleted_at')
            ->with('user')
            ->get()
            ->filter(function (Profile $profile) {
                $account = $profile->user;
                if ($account === null) {
                    return false;
                }

                return $account->auth_email_verified_at !== null
                    && (bool) $account->auth_email_enabled === true;
            })
            ->values();
    }

    /**
     * Resolve exactly one eligible Authentication Account for recovery/login-by-email.
     *
     * @param  string  $normalizedEmail  Value from normalize().
     * @return User|null  The sole eligible account, or null when zero or multiple matches.
     *
     * Failure modes (all return null — callers must not distinguish publicly):
     * - No matching profile.
     * - Profile soft-deleted or without account.
     * - Account present but email unverified or disabled.
     * - More than one eligible profile shares the same normalized email.
     *
     * Security: Returning null for both "unknown" and "ineligible" and "ambiguous"
     * prevents account-enumeration side channels when paired with uniform HTTP responses.
     */
    public static function resolveEligibleAccount(string $normalizedEmail): ?User
    {
        $matches = self::eligibleProfilesFor($normalizedEmail);

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first()->user;
    }
}
