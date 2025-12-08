<?php

namespace App\Models;

use App\Models\Academic\Student;
use App\Traits\BelongsToSchool;
use App\Traits\HasAvatar;                    // ← NEW: Unified avatar system
use App\Traits\HasCustomFields;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Guardian Model – Final Clean Version
 *
 * Now fully aligned with Student & Staff:
 *   • Personal data comes from Profile
 *   • Avatar handled by HasAvatar trait
 *   • Safe cascade delete
 */
class Guardian extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasCustomFields,
        HasTableQuery,
        HasAvatar; // ← Adds photo_url, avatarUrl(), cleanup on delete

    protected $fillable = [
        'user_id',
        'school_id',
    ];

    protected $appends = [
        // Personal data now comes from Profile + HasAvatar
        // No need to append manually
    ];

    // =================================================================
    // RELATIONSHIPS
    // =================================================================

    /**
     * The User who owns this Guardian record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The polymorphic Profile that owns this Guardian record.
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'id', 'profilable_id')
            ->where('profilable_type', self::class);
    }

    /**
     * The students (children) linked to this guardian.
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            Student::class,
            'student_guardian_pivot',
            'guardian_id',
            'student_id'
        )->withTimestamps();
    }

    // =================================================================
    // MAGIC ACCESSORS – Personal data from Profile
    // =================================================================

    public function getFullNameAttribute(): string
    {
        return $this->profile?->full_name ?? 'Unknown Guardian';
    }

    public function getShortNameAttribute(): string
    {
        return $this->profile?->short_name ?? 'Guardian';
    }

    public function getEmailAttribute(): ?string
    {
        return $this->profile?->user?->email;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->profile?->phone;
    }

    public function getGenderAttribute(): ?string
    {
        return $this->profile?->gender;
    }

    /**
     * Avatar – powered by HasAvatar trait (Spatie MediaLibrary)
     */
    public function getPhotoUrlAttribute(): string
    {
        return $this->avatarUrl('medium', $this->profile?->gender);
    }

    // =================================================================
    // CASCADE SOFT DELETE
    // =================================================================

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $guardian) {
            if ($guardian->isForceDeleting())
                return;

            // Delete the associated Profile (which will clear avatar via HasAvatar)
            if ($profile = $guardian->profile) {
                $profile->delete();
            }

            // Optional: Soft-delete User if no other profiles exist
            if ($user = $guardian->user) {
                $remainingProfiles = $user->profiles()
                    ->where('id', '!=', $profile?->id)
                    ->count();

                if ($remainingProfiles === 0) {
                    $user->delete(); // Soft delete user account
                }
            }
        });

        // Restore cascade
        static::restoring(function (self $guardian) {
            if ($profile = $guardian->profile()->withTrashed()->first()) {
                $profile->restore();
            }
        });
    }
}
