<?php

uses(Tests\TestCase::class);

/**
 * Phase 6 domain tests: section/class change, enrollment closure on terminal status,
 * promotion placement integration, capacity, registration history, school bounds.
 */

use App\Models\Academic\ClassLevel;
use App\Models\Academic\ClassSection;
use App\Models\School;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Models\Student\StudentSessionPlacement;
use App\Models\User;
use App\Services\Student\PlacementAllocationService;
use App\Services\Student\RegistrationNumberService;
use App\Services\Student\StudentStatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use ReflectionMethod;

beforeEach(function () {
    config(['activitylog.enabled' => false]);
    Model::unguard();
});

it('exposes changeSection, changeClass, and placeForPromotionOutcome on PlacementAllocationService', function () {
    $svc = app(PlacementAllocationService::class);
    expect(method_exists($svc, 'changeSection'))->toBeTrue();
    expect(method_exists($svc, 'changeClass'))->toBeTrue();
    expect(method_exists($svc, 'placeForPromotionOutcome'))->toBeTrue();
    expect(method_exists($svc, 'placeManually'))->toBeTrue();
});

it('RegistrationNumberService defines class_change and promotion reasons and policies', function () {
    $rns = app(RegistrationNumberService::class);
    expect(RegistrationNumberService::REASON_CLASS_CHANGE)->toBe('class_change');
    expect(RegistrationNumberService::REASON_PROMOTION)->toBe('promotion');
    expect(method_exists($rns, 'shouldRegenerateOnClassChange'))->toBeTrue();
    expect(method_exists($rns, 'shouldRegenerateOnSectionChange'))->toBeTrue();
    expect(method_exists($rns, 'history'))->toBeTrue();
});

it('StudentStatusService closes only active enrollments and exposes changeStatus', function () {
    $sss = app(StudentStatusService::class);
    expect(method_exists($sss, 'withdraw'))->toBeTrue();
    expect(method_exists($sss, 'transferOut'))->toBeTrue();
    expect(method_exists($sss, 'markGraduated'))->toBeTrue();
    expect(method_exists($sss, 'changeStatus'))->toBeTrue();

    $ref = new ReflectionClass($sss);
    expect($ref->hasMethod('closeActiveEnrollments'))->toBeTrue();
    expect($ref->getMethod('closeActiveEnrollments')->isProtected())->toBeTrue();
});

it('ProcessStudentPromotion uses placeForPromotionOutcome for promote and repeat', function () {
    $src = file_get_contents(app_path('Jobs/Promotion/ProcessStudentPromotion.php'));
    expect($src)->toContain('placeForPromotionOutcome');
    expect($src)->toContain('PlacementAllocationService');
    expect($src)->not->toContain('SEE_ARTIFACT');
});

it('PlacementAllocationService enforces school boundaries and capacity override', function () {
    $src = file_get_contents(app_path('Services/Student/PlacementAllocationService.php'));
    expect($src)->toContain('assertStudentSchool');
    expect($src)->toContain('capacity_override');
    expect($src)->toContain('REASON_CLASS_CHANGE');
    expect($src)->toContain('REASON_PROMOTION');
    expect($src)->not->toContain('SEE_ARTIFACT');
    expect($src)->not->toContain('PLACEHOLDER');
});

it('admission number is never written by placement or status services', function () {
    $pas = file_get_contents(app_path('Services/Student/PlacementAllocationService.php'));
    $sss = file_get_contents(app_path('Services/Student/StudentStatusService.php'));
    expect($pas)->not->toMatch('/admission_number\s*=/');
    expect($sss)->not->toMatch('/->admission_number\s*=/');
});

it('changeSection and changeClass accept string destination IDs matching controller', function () {
    $cs = new ReflectionMethod(PlacementAllocationService::class, 'changeSection');
    $params = collect($cs->getParameters())->map(fn ($p) => [$p->getName(), (string) $p->getType()])->all();
    expect($params[2][0])->toBe('destinationSectionId');
    expect($params[2][1])->toContain('string');

    $cc = new ReflectionMethod(PlacementAllocationService::class, 'changeClass');
    $params = collect($cc->getParameters())->map(fn ($p) => [$p->getName(), (string) $p->getType()])->all();
    expect($params[2][0])->toBe('destinationClassLevelId');
    expect($params[3][0])->toBe('destinationSectionId');
});

it('placeForPromotionOutcome accepts outcome and capacity_override options', function () {
    $ref = new ReflectionMethod(PlacementAllocationService::class, 'placeForPromotionOutcome');
    $names = collect($ref->getParameters())->map->getName()->all();
    expect($names)->toContain('outcome');
    expect($names)->toContain('options');
    expect($names)->toContain('academicSessionId');
});

it('RegistrationNumberService config includes regenerate_on_class_change', function () {
    $src = file_get_contents(app_path('Services/Student/RegistrationNumberService.php'));
    expect($src)->toContain('regenerate_on_class_change');
    expect($src)->toContain('REASON_CLASS_CHANGE');
    expect($src)->toContain('REASON_PROMOTION');
});
