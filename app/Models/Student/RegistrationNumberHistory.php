<?php
namespace App\Models\Student;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationNumberHistory extends Model
{
    protected $table = 'registration_number_histories';
    protected $fillable = [
        'student_id','school_id','enrollment_id','placement_id','registration_number','scope_key',
        'academic_session_id','class_level_id','class_section_id','reason','effective_from','effective_to','assigned_by','meta',
    ];
    protected $casts = ['effective_from'=>'datetime','effective_to'=>'datetime','meta'=>'array'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function enrollment(): BelongsTo { return $this->belongsTo(Enrollment::class); }
    public function placement(): BelongsTo { return $this->belongsTo(StudentSessionPlacement::class, 'placement_id'); }
    public function academicSession(): BelongsTo { return $this->belongsTo(AcademicSession::class); }
    public function classLevel(): BelongsTo { return $this->belongsTo(ClassLevel::class); }
    public function classSection(): BelongsTo { return $this->belongsTo(ClassSection::class); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
    public function scopeActive($q) { return $q->whereNull('effective_to'); }
}
