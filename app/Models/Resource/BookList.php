<?php

namespace App\Models\Resource;

use App\Models\Academic\ClassLevel;
use App\Models\Academic\Subject;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Class BookList
 *
 * Represents a book list entry in the school management system.
 *
 * @package App\Models\Resource
 * @property string $id
 * @property string $school_id
 * @property string $class_level_id
 * @property string $subject_id
 * @property string $title
 * @property string $author
 * @property string|null $isbn
 * @property string|null $edition
 * @property string|null $description
 * @property float|null $price
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class BookList extends Model implements \Spatie\MediaLibrary\HasMedia
{
    use BelongsToSchool, InteractsWithMedia, HasTableQuery, LogsActivity, SoftDeletes, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'book_lists';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'class_level_id',
        'subject_id',
        'title',
        'author',
        'isbn',
        'edition',
        'description',
        'price',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'float',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'media',
    ];

    /**
     * Columns that should never be searchable, sortable, or filterable.
     *
     * @var array<string>
     */
    protected $hiddenTableColumns = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Columns used for global search on the model.
     *
     * @var array<string>
     */
    protected $globalFilterFields = [
        'title',
        'author',
        'isbn',
        'edition',
        'description',
    ];

    /**
     * Define the relationship with the class level.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classLevel()
    {
        return $this->belongsTo(ClassLevel::class);
    }

    /**
     * Define the relationship with the subject.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('book_list')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }

    /**
     * Register media collections for the book list.
     *
     * @return void
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('book_covers')
            ->acceptsMimeTypes(['image/jpeg', 'image/png'])
            ->useDisk('public');
    }
}
