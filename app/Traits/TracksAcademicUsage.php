<?php

namespace App\Traits;

use App\Contracts\Academic\TracksAcademicUsage as TracksAcademicUsageContract;
use App\Services\Academic\AcademicPeriodUsageRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Opt-in trait for resources that depend on an Academic Session / Term.
 *
 * Synchronizes academic_period_usages on create, update (session/term change),
 * soft-delete, restore, and force-delete via Eloquent model events.
 *
 * Does NOT open its own DB transaction — relies on the owning service's
 * transaction so resource + registry mutations commit or roll back together.
 */
trait TracksAcademicUsage
{
    public static function bootTracksAcademicUsage(): void
    {
        static::created(function (Model $model) {
            if (! $model instanceof TracksAcademicUsageContract) {
                return;
            }
            static::registry()->syncFromResource($model);
        });

        static::updated(function (Model $model) {
            if (! $model instanceof TracksAcademicUsageContract) {
                return;
            }

            $relevant = $model->wasChanged('academic_session_id')
                || $model->wasChanged('term_id')
                || $model->wasChanged('school_id')
                || (method_exists($model, 'isDirty') && (
                    $model->wasChanged(static::academicUsageSessionAttribute())
                    || $model->wasChanged(static::academicUsageTermAttribute())
                ));

            // SoftDeletes sets deleted_at via update path on some versions; prefer dedicated events.
            if ($relevant) {
                static::registry()->syncFromResource($model);
            }
        });

        static::deleted(function (Model $model) {
            if (! $model instanceof TracksAcademicUsageContract) {
                return;
            }
            // Soft delete or hard delete without SoftDeletes both fire "deleted".
            if (static::modelUsesSoftDeletes($model) && ! $model->isForceDeleting()) {
                static::registry()->unregister($model);

                return;
            }

            static::registry()->unregister($model);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function (Model $model) {
                if (! $model instanceof TracksAcademicUsageContract) {
                    return;
                }
                static::registry()->syncFromResource($model);
            });

            static::forceDeleted(function (Model $model) {
                if (! $model instanceof TracksAcademicUsageContract) {
                    return;
                }
                static::registry()->unregister($model);
            });
        }
    }

    protected static function registry(): AcademicPeriodUsageRegistry
    {
        return app(AcademicPeriodUsageRegistry::class);
    }

    protected static function modelUsesSoftDeletes(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }

    /**
     * Override if the session FK column is not academic_session_id.
     */
    protected static function academicUsageSessionAttribute(): string
    {
        return 'academic_session_id';
    }

    /**
     * Override if the term FK column is not term_id.
     */
    protected static function academicUsageTermAttribute(): string
    {
        return 'term_id';
    }

    public function academicUsageSchoolId(): ?string
    {
        $id = $this->getAttribute('school_id');

        return $id !== null ? (string) $id : null;
    }

    public function academicUsageSessionId(): ?string
    {
        $id = $this->getAttribute(static::academicUsageSessionAttribute());

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    public function academicUsageTermId(): ?string
    {
        $id = $this->getAttribute(static::academicUsageTermAttribute());

        return $id !== null && $id !== '' ? (string) $id : null;
    }

    public function shouldTrackAcademicUsage(): bool
    {
        if (static::modelUsesSoftDeletes($this) && method_exists($this, 'trashed') && $this->trashed()) {
            return false;
        }

        return $this->academicUsageSessionId() !== null
            && $this->academicUsageSchoolId() !== null;
    }
}
