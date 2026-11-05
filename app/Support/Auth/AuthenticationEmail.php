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
 * - Shared emails that match more than one eligible Person/Account fail closed.
 *
 * All recovery and login-by-email paths must normalize addresses through normalize()
 * so lookup, OTP generation, notification destination, and validation use one value.
 */
final class AuthenticationEmail
{
    /**
     * Canonical lower-case trimmed email string for identity and OTP operations.
     */
    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * Profiles linked to an Authentication Account that may use this email for auth.
     *
     * @return Collection<int, Profile>
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
     * Resolve exactly one eligible Authentication Account for recovery, or null.
     *
     * Callers must treat null as fail-closed without revealing whether the email
     * exists on an ineligible account.
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
