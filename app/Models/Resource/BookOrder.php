<?php

namespace App\Models\Resource;

use App\Models\Academic\Student;
use App\Models\Model;
use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class BookOrder
 *
 * Represents a book order in the school management system.
 *
 * @package App\Models\Resource
 * @property int $id
 * @property string $school_id
 * @property int $book_list_id
 * @property string $student_id
 * @property \Illuminate\Support\Carbon $order_date
 * @property \Illuminate\Support\Carbon|null $return_date
 * @property string $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class BookOrder extends Model
{
    use BelongsToSchool, HasTableQuery, LogsActivity, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'book_orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'school_id',
        'book_list_id',
        'student_id',
        'order_date',
        'return_date',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'order_date' => 'date',
        'return_date' => 'date',
        'status' => 'string',
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
     * Columns used for global search on the model.+
     *
     *
     * @var array<string>
     */
    protected $globalFilterFields = [
        'status',
    ];

    /**
     * Define the relationship with the book list.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function book()
    {
        return $this->belongsTo(BookList::class, 'book_list_id');
    }

    /**
     * Define the relationship with the student.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the activity log options for the model.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('book_order')
            ->logFillable()
            ->logExcept(['updated_at'])
            ->logOnlyDirty();
    }
}
