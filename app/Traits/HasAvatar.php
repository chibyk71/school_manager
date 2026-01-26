<?php

namespace App\Traits;

use Spatie\Image\Enums\BorderType;
use Spatie\MediaLibrary\Conversions\Manipulations;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * HasAvatar Trait v1.0 – Unified Avatar/Photo Handling
 *
 * This trait standardizes avatar (profile photo) management across models that need it:
 *   - Profile (primary source of truth for person photos)
 *   - Student, Staff, Guardian (if they ever need independent avatars in the future)
 *
 * Why this trait exists:
 *   - Avoids duplicating media library registration logic in every model
 *   - Provides consistent API: avatarUrl(), hasAvatar(), clearAvatar()
 *   - Handles fallback avatars based on gender (or other logic)
 *   - Automatically cleans up media on model deletion
 *   - Supports responsive conversions (thumb, small, medium)
 *
 * Features / Problems Solved:
 *   - Single-file avatar collection → prevents multiple uploads per record
 *   - Gender-aware fallback images → no broken avatar icons
 *   - Automatic cleanup on delete → prevents orphaned files in storage
 *   - Consistent URL generation across frontend (Vue) and backend (emails, PDFs)
 *   - Easy to extend conversions or add new collections later
 *   - Production-ready: uses config for disk, supports webp/jpeg/png/gif
 *
 * Fits into the User Management Module:
 *   - Primarily used on Profile model (central person entity)
 *   - Student/Staff/Guardian models should delegate to their Profile's avatar
 *     (i.e. $student->profile->avatarUrl() instead of $student->avatarUrl())
 *   - Ensures photo consistency across roles (same person = same photo)
 *   - Integrates with Spatie Media Library (already in use)
 *   - Frontend consumption: PrimeVue Avatar/Image components use avatarUrl('thumb')
 *   - No direct UI — trait provides backend support for modals/tables
 *
 * Usage Examples:
 *   // In Blade / Inertia response
 *   $profile->avatarUrl('medium')
 *
 *   // In Vue component
 *   :src="profile.avatarUrl('thumb')"
 *
 *   // Clearing avatar (e.g., in controller)
 *   $profile->clearAvatar();
 *
 * Important:
 *   - Do NOT use this trait directly on Student/Staff/Guardian unless you intentionally
 *     want separate avatars per role (rare case). Prefer Profile's avatar.
 *   - Register this trait **after** InteractsWithMedia in the model
 */

trait HasAvatar
{
    use InteractsWithMedia;

    /**
     * Boot the trait: clean up media when model is deleted
     */
    public static function bootHasAvatar(): void
    {
        static::deleting(function ($model) {
            // Only clear on force delete or when soft-deleting permanently
            if ($model->isForceDeleting() || !$model->usesSoftDeletes()) {
                $model->clearMediaCollection('avatar');
            }
        });
    }

    /**
     * Define the 'avatar' media collection
     * Single file only, restricted mime types, public disk
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->useDisk(config('media-library.disk_name', 'public'));
    }

    /**
     * Define responsive conversions
     * thumb → small icons / lists
     * small → cards / profiles
     * medium → detail views / print
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->sharpen(10)
            ->border(2, BorderType::Overlay, 'white')
            ->performOnCollections('avatar');

        $this->addMediaConversion('small')
            ->width(300)
            ->height(300)
            ->sharpen(5)
            ->performOnCollections('avatar');

        $this->addMediaConversion('medium')
            ->width(600)
            ->height(600)
            ->optimize()
            ->performOnCollections('avatar');
    }

    /**
     * Get avatar URL with fallback to gender-based default
     *
     * @param string $conversion  'thumb' | 'small' | 'medium' | '' (original)
     * @param string|null $genderOverride  Override gender for fallback
     * @return string
     */
    public function avatarUrl(string $conversion = 'medium', ?string $genderOverride = null): string
    {
        $media = $this->getFirstMedia('avatar');

        if ($media) {
            return $conversion
                ? $media->getUrl($conversion)
                : $media->getUrl();
        }

        // Fallback logic
        $gender = $genderOverride ?? $this->gender ?? 'male';
        $gender = strtolower($gender);

        $defaultFile = match (true) {
            str_contains($gender, 'female') || str_contains($gender, 'woman') || str_contains($gender, 'f') => 'default-female.png',
            default => 'default-male.png',
        };

        return asset("images/avatars/{$defaultFile}");
    }

    /**
     * Check if an avatar exists (uploaded, not fallback)
     */
    public function hasAvatar(): bool
    {
        return $this->hasMedia('avatar');
    }

    /**
     * Remove the current avatar (if any)
     */
    public function clearAvatar(): void
    {
        $this->clearMediaCollection('avatar');
    }
}
