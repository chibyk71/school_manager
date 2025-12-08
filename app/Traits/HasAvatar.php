<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * HasAvatar Trait
 *
 * Standardizes avatar/photo handling across:
 *   • Profile
 *   • Student
 *   • Staff
 *   • Any future model
 *
 * Provides:
 *   ->avatarUrl($conversion = 'thumb')
 *   ->hasAvatar()
 *   ->clearAvatar()
 */
trait HasAvatar
{
    use InteractsWithMedia;

    /**
     * Boot the trait – register the 'avatar' media collection.
     */
    public static function bootHasAvatar(): void
    {
        static::deleting(function ($model) {
            $model->clearMediaCollection('avatar');
        });
    }

    /**
     * Register media collections & conversions.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->useDisk(config('media-library.disk_name', 'public'));
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->sharpen(10)
            ->performOnCollections('avatar');

        $this->addMediaConversion('small')
            ->width(300)
            ->height(300)
            ->performOnCollections('avatar');

        $this->addMediaConversion('medium')
            ->width(600)
            ->height(600)
            ->performOnCollections('avatar');
    }

    /**
     * Get avatar URL with fallback to default.
     *
     * @param string $conversion  'thumb' | 'small' | 'medium' | null (original)
     * @param string $gender      'male' | 'female' for fallback
     * @return string
     */
    public function avatarUrl(string $conversion = 'medium', ?string $gender = null): string
    {
        $media = $this->getFirstMedia('avatar');

        if ($media) {
            return $media->getUrl($conversion);
        }

        $gender = $gender ?? $this->gender ?? 'male';
        $default = in_array($gender, ['female', 'f', 'woman'])
            ? 'default-female.png'
            : 'default-male.png';

        return asset('images/avatars/' . $default);
    }

    /**
     * Check if model has an uploaded avatar.
     */
    public function hasAvatar(): bool
    {
        return $this->hasMedia('avatar');
    }

    /**
     * Remove current avatar.
     */
    public function clearAvatar(): void
    {
        $this->clearMediaCollection('avatar');
    }
}
