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

    public function test_application_based_admission_requires_class_level(): void
    {
        $application = StudentApplication::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => null,
            'academic_session_id' => $this->session->id,
            'status' => StudentApplication::STATUS_APPROVED,
            'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->createFromApplication($application, $this->school, $this->staff, [
            'acceptance_deadline' => now()->addDays(5),
        ]);
    }

    public function test_application_based_admission_requires_academic_session(): void
    {
        $application = StudentApplication::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => null,
            'status' => StudentApplication::STATUS_APPROVED,
            'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->createFromApplication($application, $this->school, $this->staff, [
            'acceptance_deadline' => now()->addDays(5),
        ]);
    }

    public function test_accept_after_deadline_runs_expiry_side_effects(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
            'acceptance_deadline' => now()->subHour(),
        ]);

        try {
            $this->service()->accept($admission, $this->staff);
            $this->fail('Expected ValidationException for expired offer');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $admission->refresh();
        $this->assertEquals(Admission::STATUS_EXPIRED, $admission->status);
        $this->assertNotNull($admission->expired_at);
    }

    public function test_reminder_not_marked_sent_when_notification_fails(): void
    {
        $admission = Admission::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => Admission::STATUS_OFFERED,
            'acceptance_deadline' => now()->addHours(12),
            'reminder_sent_at' => null,
            'application_id' => null,
            'configs' => [
                'candidate' => [
                    'email' => 'candidate@example.com',
                ],
            ],
        ]);

        // Force Notification::send to fail so safeNotify returns false.
        \Illuminate\Support\Facades\Notification::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('mail transport down'));

        $count = $this->service()->processDeadlineReminders(48, $this->school);

        $this->assertSame(0, $count);
        $admission->refresh();
        $this->assertNull($admission->reminder_sent_at);
    }

    public function test_direct_admission_blocked_when_applications_required(): void
    {
        $appService = \Mockery::mock(\App\Services\Student\StudentApplicationService::class);
        $appService->shouldReceive('applicationsRequired')->with($this->school)->andReturn(true);

        $service = new AdmissionService($appService);

        $this->expectException(ValidationException::class);
        $service->createDirect($this->school, $this->staff, [
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'acceptance_deadline' => now()->addDays(3),
        ]);
    }

    public function test_walk_in_bypass_approves_and_offers(): void
    {
        $application = StudentApplication::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => StudentApplication::STATUS_SUBMITTED,
            'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
        ]);

        $admission = $this->service()->createWalkInImmediate(
            $application,
            $this->school,
            $this->staff,
            ['acceptance_deadline' => now()->addDays(4)]
        );

        $application->refresh();
        $this->assertEquals(StudentApplication::STATUS_APPROVED, $application->status);
        $this->assertEquals(Admission::STATUS_OFFERED, $admission->status);
        $this->assertEquals($application->id, $admission->application_id);
        $this->assertTrue((bool) ($admission->configs['walk_in_bypass'] ?? false));
    }

    public function test_cross_school_class_level_rejected_on_create(): void
    {
        $otherLevel = ClassLevel::factory()->create(['school_id' => $this->otherSchool->id]);

        $application = StudentApplication::factory()->create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->session->id,
            'status' => StudentApplication::STATUS_APPROVED,
            'fee_payment_status' => StudentApplication::FEE_NOT_REQUIRED,
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->createFromApplication($application, $this->school, $this->staff, [
            'class_level_id' => $otherLevel->id,
            'acceptance_deadline' => now()->addDays(3),
        ]);
    }
}
