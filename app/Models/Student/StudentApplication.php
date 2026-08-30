<?php

namespace App\Models\Student;

use App\Models\School;
use App\Models\SchoolSection;
use App\Models\User;
use App\Traits\BelongsToSchool;
use App\Traits\HasDynamicEnum;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\Student\StudentApplicationFactory;

/**
 * StudentApplication Model – Application Entry Point (v2.0 – Production-Ready)
 *
 * This model represents a student application, whether submitted through the public portal
 * or created directly by an admin. It acts as a temporary holding area for unverified data
 * before it is reviewed and converted into a Profile + Student record upon admission.
 */

class StudentApplication extends Model
{
    use HasFactory,
        HasUuids,
        SoftDeletes,
        BelongsToSchool,
        HasDynamicEnum,
        HasTableQuery;

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'school_section_id',
        'class_level_id',
        'first_name',
        'last_name',
        'middle_name',
        'date_of_birth',
        'gender',
        'phone',
        'email',
        'nationality',
        'state_of_origin',
        'religion',
        'blood_group',
        'previous_school',
        'previous_class',
        'previous_school_address',
        'guardians_data',
        'source',
        'status',
        'application_number',
        'application_token',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
        'rejection_reason',
        'admin_notes',
        'student_id',
        'documents',
        'custom_data',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'guardians_data' => 'array',
        'documents' => 'array',
        'custom_data' => 'array',
        'status' => 'string',
        'source' => 'string',
        'gender' => 'string',
        'religion' => 'string',
        'blood_group' => 'string',
    ];

    protected array $globalFilterFields = [
        'application_number',
        'first_name',
        'last_name',
        'phone',
        'email',
        'status',
        'source',
    ];

    public function getDynamicEnumProperties(): array
    {
        return ['status', 'gender', 'religion', 'blood_group'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Academic\AcademicSession::class);
    }

    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    public function classLevel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Academic\ClassLevel::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Admissions that may result from this application (0..n).
     */
    public function admissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Admission::class, 'application_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAdmitted($query)
    {
        return $query->where('status', 'admitted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeFromPublicPortal($query)
    {
        return $query->where('source', 'public_portal');
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('academic_session_id', $sessionId);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getIsExpiredAttribute(): bool
    {
        return false;
    }

    public function generateApplicationNumber(): string
    {
        $year = now()->year;
        $prefix = 'APP';
        $sequence = str_pad($this->id ?? rand(1000, 9999), 6, '0', STR_PAD_LEFT);
        return "{$prefix}-{$year}-{$sequence}";
    }

    public function generateToken(): string
    {
        return \Str::random(64);
    }

    protected static function newFactory()
    {
        return StudentApplicationFactory::new();
    }
}
