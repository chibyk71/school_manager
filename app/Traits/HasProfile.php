<?php

namespace App\Traits;

/**
 * HasProfile Trait
 *
 * Adds beautiful magic accessors to the User model:
 *   $user->student   → returns the Student profilable model (or null)
 *   $user->staff     → returns the Staff profilable model (or null)
 *   $user->guardian  → returns the Guardian profilable model (or null)
 *   $user->profile   → returns primary Profile
 *
 * Usage:
 *   @if(auth()->user()->student) ... @endif
 *   {{ auth()->user()->staff->staff_id_number }}
 */
trait HasProfile
{
    /**
     * Get the user's primary profile.
     */
    public function profile()
    {
        return $this->primaryProfile?->profilable;
    }

    /**
     * Get the student's profilable record if exists.
     */
    public function student()
    { return $this->getProfilable('student');    }

    /**
     * Get the staff's profilable record if exists.
     */
    public function staff()        { return $this->getProfilable('staff');      }

    /**
     * Get the guardian's profilable record if exists.
     */
    public function guardian()     { return $this->getProfilable('guardian');   }

    /**
     * Generic helper used by magic methods above.
     *
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    private function getProfilable(string $type)
    {
        $profile = $this->profiles()
            ->where('profile_type', $type)
            ->first();

        return $profile?->profilable;
    }

    /**
     * Check if user has a specific profile type.
     */
    public function hasProfile(string $type): bool
    { return (bool) $this->getProfilable($type); }

    public function isStudent(): bool   { return $this->hasProfile('student');   }
    public function isStaff(): bool     { return $this->hasProfile('staff');     }
    public function isGuardian(): bool  { return $this->hasProfile('guardian');  }
}
