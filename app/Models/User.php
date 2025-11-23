<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use App\Models\Academic\Student;
use App\Models\Employee\Department;
use App\Models\Employee\Staff;
use App\Models\Finance\FeeConcession;
use App\Models\Transport\Route;
use App\Models\Transport\Vehicle\Vehicle;
use App\Notifications\TimeTableGeneratedNotification;
use App\Support\DepartmentCategories;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laratrust\Contracts\LaratrustUser;
use Laratrust\Traits\HasRolesAndPermissions;
use RuangDeveloper\LaravelSettings\Traits\HasSettings;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable implements LaratrustUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasSettings, CausesActivity, HasRolesAndPermissions, HasUuids, Filterable, Sortable, HasTableQuery, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'enrollment_id',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes used for global filtering.
     *
     * @var array<string>
     */
    protected array $globalFilterFields = ['name', 'username', 'email'];

    /**
     * The attributes hidden from table queries.
     *
     * @var array<string>
     */
    protected array $hiddenTableColumns = ['password', 'remember_token', 'created_at', 'updated_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function schools()
    {
        return $this->belongsToMany(School::class, 'school_users');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_user');
    }

    /**
     * The feeConcessions that belong to the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function feeConcessions()
    {
        return $this->belongsToMany(FeeConcession::class, 'user_fee_concessions', 'user_id', 'fee_concession_id');
    }

    /**
     * The routes that belong to the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_vehicle', 'user_id', 'route_id')
            ->withPivot('vehicle_id');
    }

    /**
     * The vehicles that belong to the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'route_vehicle', 'user_id', 'vehicle_id')->withPivot('route_id');
    }

    /**
     * Get the student associated with the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function student()
    {
        return $this->hasOne(Student::class, 'user_id', 'id');
    }

    /**
     * Get the teacher associated with the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function teacher()
    {
        return $this->hasOne(Staff::class, 'user_id', 'id');
    }

    /**
     * Get the guardian associated with the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function guardian()
    {
        return $this->hasOne(Guardian::class, 'user_id', 'id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly(['name', 'email'])
            ->setDescriptionForEvent(fn(string $eventName) => "User {$this->name} was {$eventName}");
    }

    /**
     * Get the user's primary department category based on roles.
     * Resolves conflicts by selecting the category with the lowest priority.
     *
     * @return string|null The primary category key or null if no roles.
     */
    public function getPrimaryCategory(): ?string
    {
        $userCategories = $this->departments()->pluck('category')->unique()->filter();

        if ($userCategories->isEmpty()) {
            return null;
        }

        $minPriority = PHP_INT_MAX;
        $primary = null;

        foreach ($userCategories as $cat) {
            if (!DepartmentCategories::isValid($cat)) {
                continue; // Skip invalid categories
            }

            $prio = DepartmentCategories::getPriority($cat);

            if ($prio < $minPriority) {
                $minPriority = $prio;
                $primary = $cat;
            }
        }

        return $primary;
    }

    public function receiveBroadcastNotifications()
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable')
            ->where('type', TimeTableGeneratedNotification::class)
            ->each(function ($notification) {
                activity()
                    ->performedOn($this)
                    ->causedBy($this)
                    ->log("Received timetable notification for timetable ID {$notification->data['time_table_id']}");
            });
    }
}
