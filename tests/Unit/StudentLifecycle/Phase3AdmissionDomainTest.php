<?php

namespace Tests\Unit\StudentLifecycle;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassLevel;
use App\Models\School;
use App\Models\Student\Admission;
use App\Models\Student\StudentApplication;
use App\Models\User;
use App\Services\Student\AdmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase3AdmissionDomainTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;
    protected School $otherSchool;
    protected ClassLevel $classLevel;
    protected AcademicSession $session;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->otherSchool = School::factory()->create();
        $this->classLevel = ClassLevel::factory()->create(['school_id' => $this->school->id]);
        $this->session = AcademicSession::factory()->create(['school_id' => $this->school->id]);
        $this->staff = User::factory()->create();
    }

    protected function service(): AdmissionService
    {
        return app(AdmissionService::class);
    }

    public function test_admission_can_exist_without_application(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'application_id' => null,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
        ]);

        $this->assertNull($admission->application_id);
        $this->assertNull($admission->student_id);
    }

    public function test_admission_can_exist_without_student(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'student_id' => null,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
        ]);

        $this->assertNull($admission->student_id);
    }

    public function test_valid_status_transitions_succeed(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
            'acceptance_deadline' => now()->addDays(7),
        ]);

        $this->assertTrue($admission->canTransitionTo(Admission::STATUS_ACCEPTED));
        $this->assertTrue($admission->canTransitionTo(Admission::STATUS_DECLINED));
        $this->assertTrue($admission->canTransitionTo(Admission::STATUS_CANCELLED));
    }

    public function test_invalid_status_transitions_fail(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_ACCEPTED,
        ]);

        $this->assertFalse($admission->canTransitionTo(Admission::STATUS_OFFERED));
        $this->expectException(ValidationException::class);
        $admission->transitionTo(Admission::STATUS_DECLINED);
    }

    public function test_school_consistency_enforced(): void
    {
        $otherLevel = ClassLevel::factory()->create(['school_id' => $this->otherSchool->id]);

        $this->expectException(\InvalidArgumentException::class);

        $admission = new Admission([
            'school_id' => $this->school->id,
            'class_level_id' => $otherLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
        ]);
        $admission->save();
    }

    public function test_approved_application_can_produce_admission(): void
    {
        $application = StudentApplication::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => StudentApplication::STATUS_APPROVED,
            'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
        ]);

        $admission = $this->service()->createFromApplication(
            $application,
            $this->school,
            $this->staff,
            ['acceptance_deadline' => now()->addDays(10)]
        );

        $this->assertEquals(Admission::STATUS_OFFERED, $admission->status);
        $this->assertEquals($application->id, $admission->application_id);
        $this->assertNull($admission->student_id);
        $this->assertNotNull($admission->offered_at);
    }

    public function test_unapproved_application_cannot_produce_admission(): void
    {
        $application = StudentApplication::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => StudentApplication::STATUS_SUBMITTED,
            'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->createFromApplication($application, $this->school, $this->staff);
    }

    public function test_cross_school_application_rejected(): void
    {
        $application = StudentApplication::factory()->create([
            'school_id' => $this->otherSchool->id,
            'status' => StudentApplication::STATUS_APPROVED,
            'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->createFromApplication($application, $this->school, $this->staff);
    }

    public function test_multiple_approved_applications_can_produce_separate_admissions(): void
    {
        $app1 = StudentApplication::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => StudentApplication::STATUS_APPROVED,
            'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
            'first_name' => 'A',
            'last_name' => 'One',
        ]);
        $app2 = StudentApplication::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => StudentApplication::STATUS_APPROVED,
            'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
            'first_name' => 'A',
            'last_name' => 'Two',
        ]);

        $a1 = $this->service()->createFromApplication($app1, $this->school, $this->staff, [
            'acceptance_deadline' => now()->addDays(5),
        ]);
        $a2 = $this->service()->createFromApplication($app2, $this->school, $this->staff, [
            'acceptance_deadline' => now()->addDays(5),
        ]);

        $this->assertNotEquals($a1->id, $a2->id);
        $this->assertEquals($app1->id, $a1->application_id);
        $this->assertEquals($app2->id, $a2->application_id);
    }

    public function test_duplicate_active_offer_for_same_application_blocked(): void
    {
        $application = StudentApplication::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => StudentApplication::STATUS_APPROVED,
            'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
        ]);

        $this->service()->createFromApplication($application, $this->school, $this->staff, [
            'acceptance_deadline' => now()->addDays(5),
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->createFromApplication($application, $this->school, $this->staff, [
            'acceptance_deadline' => now()->addDays(5),
        ]);
    }

    public function test_direct_admission_when_applications_not_required(): void
    {
        $admission = $this->service()->createDirect($this->school, $this->staff, [
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'acceptance_deadline' => now()->addDays(7),
            'first_name' => 'Walk',
            'last_name' => 'In',
            'email' => 'walk@example.com',
        ]);

        $this->assertNull($admission->application_id);
        $this->assertEquals(Admission::STATUS_OFFERED, $admission->status);
        $this->assertEquals('direct', $admission->configs['created_via'] ?? null);
    }

    public function test_accept_valid_offer(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
            'acceptance_deadline' => now()->addDays(3),
        ]);

        $result = $this->service()->accept($admission, $this->staff);

        $this->assertEquals(Admission::STATUS_ACCEPTED, $result->status);
        $this->assertNotNull($result->accepted_at);
        $this->assertNull($result->student_id);
    }

    public function test_expired_offer_cannot_be_accepted(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
            'acceptance_deadline' => now()->subHour(),
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->accept($admission, $this->staff);
    }

    public function test_decline_valid_offer(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
            'acceptance_deadline' => now()->addDays(3),
        ]);

        $result = $this->service()->decline($admission, $this->staff, 'Chose another school');

        $this->assertEquals(Admission::STATUS_DECLINED, $result->status);
        $this->assertNotNull($result->declined_at);
    }

    public function test_decline_does_not_affect_unrelated_admissions(): void
    {
        $a1 = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
            'acceptance_deadline' => now()->addDays(3),
        ]);
        $a2 = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
            'acceptance_deadline' => now()->addDays(3),
        ]);

        $this->service()->decline($a1, $this->staff);
        $a2->refresh();

        $this->assertEquals(Admission::STATUS_OFFERED, $a2->status);
    }

    public function test_expiry_for_past_deadline(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
            'acceptance_deadline' => now()->subMinute(),
        ]);

        $result = $this->service()->expire($admission);

        $this->assertEquals(Admission::STATUS_EXPIRED, $result->status);
        $this->assertNotNull($result->expired_at);
    }

    public function test_accepted_offer_does_not_expire(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_ACCEPTED,
            'acceptance_deadline' => now()->subDay(),
            'accepted_at' => now()->subDays(2),
        ]);

        $result = $this->service()->expire($admission);
        $this->assertEquals(Admission::STATUS_ACCEPTED, $result->status);
    }

    public function test_expiry_is_idempotent(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
            'acceptance_deadline' => now()->subMinute(),
        ]);

        $first = $this->service()->expire($admission);
        $second = $this->service()->expire($first);

        $this->assertEquals(Admission::STATUS_EXPIRED, $second->status);
    }

    public function test_terminal_offer_cannot_be_declined_again(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_DECLINED,
            'declined_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->decline($admission, $this->staff);
    }
}
