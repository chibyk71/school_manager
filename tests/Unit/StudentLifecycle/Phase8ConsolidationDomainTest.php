<?php

uses(Tests\TestCase::class);

/**
 * Phase 8 consolidation smoke tests.
 */

test('obsolete StudentEnrollmentService classes are removed', function () {
    expect(class_exists(\App\Services\Student\StudentEnrollmentService::class))->toBeFalse();
    expect(class_exists(\App\Services\UserManagement\StudentEnrollmentService::class))->toBeFalse();
});

test('Academic Student is compatibility subclass of canonical Student', function () {
    expect(is_subclass_of(\App\Models\Academic\Student::class, \App\Models\Student\Student::class))->toBeTrue();
    expect((new \App\Models\Academic\Student())->getTable())->toBe((new \App\Models\Student\Student())->getTable());
});
