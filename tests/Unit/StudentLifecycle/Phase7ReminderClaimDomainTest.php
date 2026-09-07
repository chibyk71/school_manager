<?php

uses(Tests\TestCase::class);

/**
 * Phase 7 — reminder claim / idempotency lifecycle.
 *
 * Exercises the real notify()/lock/suppress path where practical, not only
 * pre-seeded NotificationLog rows.
 *
 * Helpers are phase-claim-prefixed (p7c*) so the full StudentLifecycle suite
 * can load without global function redeclaration collisions.
 */

use App\Models\NotificationLog;
use App\Models\School;
use App\Models\Student\Enrollment;
use App\Notifications\Student\EnrollmentIncompleteNotification;
use App\Services\SmsService;
use App\Services\Student\LifecycleNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildReminderClaimSchema();
    Notification::fake();
    Cache::flush();
});

afterEach(function () {
    \Mockery::close();
    dropReminderClaimSchema();
});

function dropReminderClaimSchema(): void
{
    foreach (['notification_logs', 'enrollments', 'schools', 'settings'] as $table) {
        Schema::dropIfExists($table);
    }
}

function buildReminderClaimSchema(): void
{
    dropReminderClaimSchema();

    Schema::create('settings', function (Blueprint $t) {
        $t->id();
        $t->string('key');
        $t->json('value')->nullable();
        $t->nullableUuidMorphs('model');
        $t->timestamps();
    });

    // Match minimum columns required by School::creating (slug + data) and soft deletes.
    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->string('slug')->unique();
        $t->string('code')->nullable();
        $t->json('data')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('enrollments', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id');
        $t->string('status')->default('draft');
        $t->json('meta')->nullable();
        $t->timestamps();
    });

    Schema::create('notification_logs', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('school_id')->nullable();
        $t->string('notification_type')->nullable();
        $t->string('notifiable_type')->nullable();
        $t->string('notifiable_id')->nullable();
        $t->string('channel')->nullable();
        $t->string('provider')->nullable();
        $t->string('recipient')->nullable();
        $t->text('message')->nullable();
        $t->boolean('success')->default(false);
        $t->text('error')->nullable();
        $t->unsignedInteger('segments')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamp('delivered_at')->nullable();
        $t->timestamps();
    });
}

function p7cSchool(string $name = 'Claim Test School'): School
{
    return School::query()->create([
        'id' => (string) Str::uuid(),
        'name' => $name,
    ]);
}

function p7cEnrollment(School $school, array $biodata = []): Enrollment
{
    return Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'status' => 'draft',
        'meta' => [
            'biodata' => array_merge([
                'email' => 'parent@example.com',
                'phone' => '08012345678',
            ], $biodata),
        ],
    ]);
}

function seedSmsEnabled(School $school): void
{
    DB::table('settings')->insert([
        'key' => 'sms',
        'value' => json_encode([
            'enabled' => true,
            'providers' => ['log' => ['driver' => 'log']],
        ]),
        'model_type' => School::class,
        'model_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function mailRecipient(string $email = 'parent@example.com'): AnonymousNotifiable
{
    return (new AnonymousNotifiable)->route('mail', $email);
}

function smsRecipient(string $phone = '08012345678'): AnonymousNotifiable
{
    return (new AnonymousNotifiable)->route('sms', $phone);
}

it('creates school with slug via model boot when fixture has slug column', function () {
    $school = p7cSchool('Alpha Secondary');
    expect($school->slug)->not->toBeEmpty()
        ->and($school->exists)->toBeTrue();
});

it('does not create claim-phase rows for non-reminder notifications', function () {
    $school = p7cSchool();
    $enrollment = p7cEnrollment($school);

    $svc = app(LifecycleNotificationService::class);
    $svc->notify(
        $school,
        'enrollment_finalized',
        EnrollmentIncompleteNotification::class,
        $enrollment,
        ['audience' => 'parent']
    );

    expect(NotificationLog::query()->where('metadata->phase', 'claimed')->count())->toBe(0);
});

it('suppresses same channel after successful delivery via notify path', function () {
    $school = p7cSchool();
    $enrollment = p7cEnrollment($school, ['email' => 'ok@example.com', 'phone' => null]);

    $svc = app(LifecycleNotificationService::class);
    $key = 'enrollment_incomplete:'.$enrollment->id;

    $sent = $svc->notify(
        $school,
        'enrollment_incomplete_reminder',
        EnrollmentIncompleteNotification::class,
        $enrollment,
        [
            'audience' => 'parent',
            'reminder' => true,
            'reminder_key' => $key,
        ]
    );

    expect($sent)->toBeGreaterThan(0);

    $audienceKey = $key.':parent';
    $recipient = mailRecipient('ok@example.com');

    expect($svc->shouldSuppressReminder(
        $school,
        $enrollment,
        EnrollmentIncompleteNotification::class,
        $audienceKey,
        $recipient,
        'mail'
    ))->toBeTrue();

    expect($svc->shouldSuppressReminder(
        $school,
        $enrollment,
        EnrollmentIncompleteNotification::class,
        $audienceKey,
        $recipient,
        'sms'
    ))->toBeFalse();
});

it('allows retry after a real failed SMS dispatch through notify', function () {
    $school = p7cSchool();
    seedSmsEnabled($school);
    $enrollment = p7cEnrollment($school, [
        'email' => null,
        'phone' => '08099998888',
    ]);

    $key = 'enrollment_incomplete:'.$enrollment->id;
    $audienceKey = $key.':parent';

    $failingSms = \Mockery::mock(SmsService::class);
    $failingSms->shouldReceive('send')->once()->andReturn(false);
    $svcFail = new LifecycleNotificationService($failingSms);

    $sent1 = $svcFail->notify(
        $school,
        'enrollment_incomplete_reminder',
        EnrollmentIncompleteNotification::class,
        $enrollment,
        [
            'audience' => 'parent',
            'reminder' => true,
            'reminder_key' => $key,
        ]
    );
    expect($sent1)->toBe(0);

    $failed = NotificationLog::query()
        ->where('school_id', $school->id)
        ->where('channel', 'sms')
        ->where('success', false)
        ->where('metadata->reminder_key', $audienceKey)
        ->count();
    expect($failed)->toBeGreaterThan(0);

    expect(
        NotificationLog::query()->where('metadata->phase', 'claimed')->count()
    )->toBe(0);

    $recipient = smsRecipient('08099998888');
    $probe = new LifecycleNotificationService(\Mockery::mock(SmsService::class));
    expect($probe->shouldSuppressReminder(
        $school,
        $enrollment,
        EnrollmentIncompleteNotification::class,
        $audienceKey,
        $recipient,
        'sms'
    ))->toBeFalse();

    $okSms = \Mockery::mock(SmsService::class);
    $okSms->shouldReceive('send')->once()->andReturn(true);
    $svcOk = new LifecycleNotificationService($okSms);

    $sent2 = $svcOk->notify(
        $school,
        'enrollment_incomplete_reminder',
        EnrollmentIncompleteNotification::class,
        $enrollment,
        [
            'audience' => 'parent',
            'reminder' => true,
            'reminder_key' => $key,
        ]
    );
    expect($sent2)->toBeGreaterThan(0);

    expect($probe->shouldSuppressReminder(
        $school,
        $enrollment,
        EnrollmentIncompleteNotification::class,
        $audienceKey,
        $recipient,
        'sms'
    ))->toBeTrue();
});

it('treats only phase=dispatched as in-flight; legacy claimed does not suppress', function () {
    $school = p7cSchool();
    $enrollment = p7cEnrollment($school);
    $svc = app(LifecycleNotificationService::class);
    $key = 'enrollment_incomplete:'.$enrollment->id.':parent';
    $recipient = mailRecipient();

    NotificationLog::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'notification_type' => EnrollmentIncompleteNotification::class,
        'channel' => 'claim',
        'recipient' => 'mail:parent@example.com',
        'message' => 'legacy claim',
        'success' => false,
        'metadata' => [
            'reminder_key' => $key,
            'lifecycle_type' => $enrollment->getMorphClass(),
            'lifecycle_id' => (string) $enrollment->id,
            'phase' => 'claimed',
            'channel' => 'claim',
        ],
    ]);

    expect($svc->hasPendingDispatch(
        $school, $enrollment, EnrollmentIncompleteNotification::class, $key, $recipient, 'mail'
    ))->toBeFalse();

    expect($svc->shouldSuppressReminder(
        $school, $enrollment, EnrollmentIncompleteNotification::class, $key, $recipient, 'mail'
    ))->toBeFalse();

    NotificationLog::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'notification_type' => EnrollmentIncompleteNotification::class,
        'channel' => 'mail',
        'recipient' => 'mail:parent@example.com',
        'message' => 'queued',
        'success' => false,
        'metadata' => [
            'reminder_key' => $key,
            'lifecycle_type' => $enrollment->getMorphClass(),
            'lifecycle_id' => (string) $enrollment->id,
            'phase' => 'dispatched',
            'channel' => 'mail',
            'dispatch_id' => (string) Str::uuid(),
        ],
    ]);

    expect($svc->hasPendingDispatch(
        $school, $enrollment, EnrollmentIncompleteNotification::class, $key, $recipient, 'mail'
    ))->toBeTrue();
});

it('keeps parent and admin reminder keys independent after notify', function () {
    $school = p7cSchool();
    $enrollment = p7cEnrollment($school, ['email' => 'p@example.com', 'phone' => null]);
    $svc = app(LifecycleNotificationService::class);
    $base = 'enrollment_incomplete:'.$enrollment->id;

    $svc->notify(
        $school,
        'enrollment_incomplete_reminder',
        EnrollmentIncompleteNotification::class,
        $enrollment,
        ['audience' => 'parent', 'reminder' => true, 'reminder_key' => $base]
    );

    $recipient = mailRecipient('p@example.com');
    expect($svc->shouldSuppressReminder(
        $school, $enrollment, EnrollmentIncompleteNotification::class, $base.':parent', $recipient, 'mail'
    ))->toBeTrue();

    expect($svc->shouldSuppressReminder(
        $school, $enrollment, EnrollmentIncompleteNotification::class, $base.':admin', $recipient, 'mail'
    ))->toBeFalse();
});

it('uses distinct lock keys per channel and audience', function () {
    $school = p7cSchool();
    $enrollment = p7cEnrollment($school);
    $svc = app(LifecycleNotificationService::class);
    $parentKey = 'enrollment_incomplete:'.$enrollment->id.':parent';
    $adminKey = 'enrollment_incomplete:'.$enrollment->id.':admin';
    $recipient = mailRecipient();

    $mailParent = $svc->reminderLockKey($school, $enrollment, EnrollmentIncompleteNotification::class, $parentKey, $recipient, 'mail');
    $smsParent = $svc->reminderLockKey($school, $enrollment, EnrollmentIncompleteNotification::class, $parentKey, $recipient, 'sms');
    $mailAdmin = $svc->reminderLockKey($school, $enrollment, EnrollmentIncompleteNotification::class, $adminKey, $recipient, 'mail');

    expect($mailParent)->not->toBe($smsParent)
        ->and($mailParent)->not->toBe($mailAdmin);
});

it('rejects a second concurrent lock acquisition for the same reminder slot', function () {
    $school = p7cSchool();
    $enrollment = p7cEnrollment($school);
    $svc = app(LifecycleNotificationService::class);
    $key = 'enrollment_incomplete:'.$enrollment->id.':parent';
    $recipient = mailRecipient();

    $lockKey = $svc->reminderLockKey(
        $school,
        $enrollment,
        EnrollmentIncompleteNotification::class,
        $key,
        $recipient,
        'mail'
    );

    $first = Cache::lock($lockKey, 30);
    expect($first->get())->toBeTrue();

    $second = Cache::lock($lockKey, 30);
    expect($second->get())->toBeFalse();

    $first->release();
    expect($second->get())->toBeTrue();
    $second->release();
});
