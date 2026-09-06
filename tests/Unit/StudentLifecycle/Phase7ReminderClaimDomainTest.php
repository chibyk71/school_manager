<?php

uses(Tests\TestCase::class);

/**
 * Phase 7 — reminder claim / idempotency lifecycle.
 *
 * Invariants:
 * - failed dispatch remains retryable (no stuck phase=claimed)
 * - successful delivery suppresses subsequent reminder for that channel
 * - parent and admin keys are independent
 * - mail and SMS do not suppress each other
 * - non-reminder notifications create no claim rows
 * - concurrent lock prevents double claim of the same slot
 */

use App\Models\NotificationLog;
use App\Models\School;
use App\Models\Student\Enrollment;
use App\Notifications\Student\EnrollmentIncompleteNotification;
use App\Services\Student\LifecycleNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
    buildReminderClaimSchema();
    Notification::fake();
    Cache::flush();
});

afterEach(function () {
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

    Schema::create('schools', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->timestamps();
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

function makeSchool(): School
{
    return School::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Claim Test School',
    ]);
}

function makeEnrollment(School $school, array $meta = []): Enrollment
{
    return Enrollment::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'status' => 'draft',
        'meta' => array_merge([
            'biodata' => [
                'email' => 'parent@example.com',
                'phone' => '08012345678',
            ],
        ], $meta),
    ]);
}

function enableLifecyclePrefs(School $school): void
{
    \DB::table('settings')->insert([
        'key' => 'general.notifications',
        'value' => json_encode([
            'enrollment_incomplete_reminder' => ['parent' => true, 'admin' => true],
            'enrollment_finalized' => ['parent' => true, 'admin' => false],
        ]),
        'model_type' => School::class,
        'model_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('does not create claim-phase rows for non-reminder notifications', function () {
    $school = makeSchool();
    enableLifecyclePrefs($school);
    $enrollment = makeEnrollment($school);

    $svc = app(LifecycleNotificationService::class);
    $svc->notify(
        $school,
        'enrollment_finalized',
        EnrollmentIncompleteNotification::class,
        $enrollment,
        ['audience' => 'parent']
    );

    expect(
        NotificationLog::query()->where('metadata->phase', 'claimed')->count()
    )->toBe(0);
});

it('suppresses subsequent reminder after successful delivery for same channel', function () {
    $school = makeSchool();
    enableLifecyclePrefs($school);
    $enrollment = makeEnrollment($school);
    $svc = app(LifecycleNotificationService::class);
    $key = 'enrollment_incomplete:'.$enrollment->id.':parent';

    NotificationLog::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'notification_type' => EnrollmentIncompleteNotification::class,
        'channel' => 'mail',
        'recipient' => 'mail:parent@example.com',
        'message' => 'ok',
        'success' => true,
        'metadata' => [
            'reminder_key' => $key,
            'lifecycle_type' => $enrollment->getMorphClass(),
            'lifecycle_id' => (string) $enrollment->id,
            'phase' => 'delivered',
            'channel' => 'mail',
        ],
        'delivered_at' => now(),
    ]);

    $recipient = (new AnonymousNotifiable)->route('mail', 'parent@example.com');

    expect($svc->shouldSuppressReminder(
        $school,
        $enrollment,
        EnrollmentIncompleteNotification::class,
        $key,
        $recipient,
        'mail'
    ))->toBeTrue();

    // SMS channel must remain eligible (mail success must not suppress SMS).
    expect($svc->shouldSuppressReminder(
        $school,
        $enrollment,
        EnrollmentIncompleteNotification::class,
        $key,
        $recipient,
        'sms'
    ))->toBeFalse();
});

it('allows retry after failed dispatch (no stuck claimed phase)', function () {
    $school = makeSchool();
    enableLifecyclePrefs($school);
    $enrollment = makeEnrollment($school);
    $svc = app(LifecycleNotificationService::class);
    $key = 'enrollment_incomplete:'.$enrollment->id.':parent';
    $recipient = (new AnonymousNotifiable)->route('mail', 'parent@example.com');

    NotificationLog::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'notification_type' => EnrollmentIncompleteNotification::class,
        'channel' => 'mail',
        'recipient' => 'mail:parent@example.com',
        'message' => 'fail',
        'success' => false,
        'error' => 'SMTP down',
        'metadata' => [
            'reminder_key' => $key,
            'lifecycle_type' => $enrollment->getMorphClass(),
            'lifecycle_id' => (string) $enrollment->id,
            'phase' => 'dispatch_failed',
            'channel' => 'mail',
        ],
    ]);

    // Legacy stuck claim row (pre-fix) should not block either.
    NotificationLog::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'notification_type' => EnrollmentIncompleteNotification::class,
        'channel' => 'claim',
        'recipient' => 'mail:parent@example.com',
        'message' => 'claim',
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
        $school,
        $enrollment,
        EnrollmentIncompleteNotification::class,
        $key,
        $recipient,
        'mail'
    ))->toBeFalse();

    expect($svc->shouldSuppressReminder(
        $school,
        $enrollment,
        EnrollmentIncompleteNotification::class,
        $key,
        $recipient,
        'mail'
    ))->toBeFalse();
});

it('suppresses while a dispatch is genuinely in-flight', function () {
    $school = makeSchool();
    $enrollment = makeEnrollment($school);
    $svc = app(LifecycleNotificationService::class);
    $key = 'enrollment_incomplete:'.$enrollment->id.':parent';
    $recipient = (new AnonymousNotifiable)->route('mail', 'parent@example.com');

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
        $school,
        $enrollment,
        EnrollmentIncompleteNotification::class,
        $key,
        $recipient,
        'mail'
    ))->toBeTrue();
});

it('keeps parent and admin reminder keys independent', function () {
    $school = makeSchool();
    $enrollment = makeEnrollment($school);
    $svc = app(LifecycleNotificationService::class);
    $parentKey = 'enrollment_incomplete:'.$enrollment->id.':parent';
    $adminKey = 'enrollment_incomplete:'.$enrollment->id.':admin';
    $recipient = (new AnonymousNotifiable)->route('mail', 'parent@example.com');

    NotificationLog::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'notification_type' => EnrollmentIncompleteNotification::class,
        'channel' => 'mail',
        'recipient' => 'mail:parent@example.com',
        'message' => 'ok',
        'success' => true,
        'metadata' => [
            'reminder_key' => $parentKey,
            'lifecycle_type' => $enrollment->getMorphClass(),
            'lifecycle_id' => (string) $enrollment->id,
            'phase' => 'delivered',
            'channel' => 'mail',
        ],
        'delivered_at' => now(),
    ]);

    expect($svc->shouldSuppressReminder(
        $school, $enrollment, EnrollmentIncompleteNotification::class, $parentKey, $recipient, 'mail'
    ))->toBeTrue();

    expect($svc->shouldSuppressReminder(
        $school, $enrollment, EnrollmentIncompleteNotification::class, $adminKey, $recipient, 'mail'
    ))->toBeFalse();
});

it('uses distinct lock keys per channel and audience', function () {
    $school = makeSchool();
    $enrollment = makeEnrollment($school);
    $svc = app(LifecycleNotificationService::class);
    $parentKey = 'enrollment_incomplete:'.$enrollment->id.':parent';
    $adminKey = 'enrollment_incomplete:'.$enrollment->id.':admin';
    $recipient = (new AnonymousNotifiable)->route('mail', 'parent@example.com');

    $mailParent = $svc->reminderLockKey($school, $enrollment, EnrollmentIncompleteNotification::class, $parentKey, $recipient, 'mail');
    $smsParent = $svc->reminderLockKey($school, $enrollment, EnrollmentIncompleteNotification::class, $parentKey, $recipient, 'sms');
    $mailAdmin = $svc->reminderLockKey($school, $enrollment, EnrollmentIncompleteNotification::class, $adminKey, $recipient, 'mail');

    expect($mailParent)->not->toBe($smsParent);
    expect($mailParent)->not->toBe($mailAdmin);
});

it('rejects a second lock acquisition for the same reminder slot', function () {
    $school = makeSchool();
    $enrollment = makeEnrollment($school);
    $svc = app(LifecycleNotificationService::class);
    $key = 'enrollment_incomplete:'.$enrollment->id.':parent';
    $recipient = (new AnonymousNotifiable)->route('mail', 'parent@example.com');

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
