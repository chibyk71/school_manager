<?php

/**
 * Documentation Module - Docent site access, rendering, authorization leakage.
 *
 * Focused schema (same rationale as Permission Phase HTTP tests): full SQLite
 * migrate is broken by an unrelated devices migration. These tests still exercise
 * the real HTTP routes, Gate -> AuthorizationService bridge, and Docent helpers.
 */

use App\Models\School;
use App\Models\User;
use App\Services\Permission\AuthorizationService;
use App\Services\Permission\EffectiveRoleResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use STS\Docent\Testing\InteractsWithDocs;

uses(InteractsWithDocs::class);

beforeEach(function () {
    config([
        'activitylog.enabled' => false,
        'app.key' => 'base64:2fl+KtvkdphvQyVfipPSU5l4l+4YfYm9d4qTjJ3b8/E=',
        'laratrust.permissions_as_gates' => false,
        'docent.insights.enabled' => false,
        'docent.ai.enabled' => false,
        'docent.database.enabled' => false,
    ]);

    Model::unguard();
    docsBuildSchema();
    docsClearSchoolContext();

    $this->withoutVite();
    $this->withoutMiddleware([
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\EnsureCurrentSession::class,
        \App\Http\Middleware\CheckMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

    app()->instance(
        \App\Contracts\Authorization\Authorization::class,
        new AuthorizationService(new EffectiveRoleResolver())
    );
});

afterEach(function () {
    docsClearSchoolContext();
    docsDropSchema();
});

function docsBuildSchema(): void
{
    DB::statement('PRAGMA foreign_keys = ON');

    foreach ([
        'permission_user', 'role_user', 'permission_role', 'permissions', 'roles',
        'schools', 'users', 'docent_insight_events',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('users', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('schools', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('code')->nullable();
        $table->boolean('is_active')->default(true);
        $table->json('data')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('display_name')->nullable();
        $table->string('description')->nullable();
        $table->boolean('disabled')->default(false);
        $table->uuid('school_id')->nullable();
        $table->timestamps();
    });

    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->string('display_name')->nullable();
        $table->string('description')->nullable();
        $table->timestamps();
    });

    Schema::create('permission_role', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    Schema::create('role_user', function (Blueprint $table) {
        $table->uuid('role_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->uuid('school_id')->nullable();
        $table->unique(['user_id', 'role_id', 'user_type', 'school_id'], 'role_user_user_role_type_school_unique');
    });

    Schema::create('permission_user', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->uuid('user_id');
        $table->string('user_type');
        $table->uuid('school_id')->nullable();
        $table->unique(['user_id', 'permission_id', 'user_type', 'school_id'], 'permission_user_unique');
    });

    Schema::create('docent_insight_events', function (Blueprint $table) {
        $table->id();
        $table->string('site')->default('docs')->index();
        $table->uuid('event_id')->unique();
        $table->string('category', 20)->index();
        $table->string('event', 40)->index();
        $table->string('surface', 16);
        $table->string('page_slug')->nullable();
        $table->string('query', 500)->nullable();
        $table->uuid('search_id')->nullable();
        $table->string('reference_id', 64)->nullable();
        $table->string('target_slug')->nullable();
        $table->unsignedSmallInteger('result_count')->nullable();
        $table->json('result_slugs')->nullable();
        $table->string('status', 20)->nullable();
        $table->json('citations')->nullable();
        $table->string('feedback', 4)->nullable();
        $table->timestamp('created_at')->useCurrent();
    });

    foreach (['student.view', 'student.update', 'student.delete', 'student.restore', 'dashboard.view'] as $name) {
        DB::table('permissions')->insert([
            'name' => $name,
            'display_name' => $name,
            'description' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function docsDropSchema(): void
{
    foreach ([
        'permission_user', 'role_user', 'permission_role', 'permissions', 'roles',
        'schools', 'users', 'docent_insight_events',
    ] as $table) {
        Schema::dropIfExists($table);
    }
}

function docsClearSchoolContext(): void
{
    if (app()->bound('schoolManager')) {
        try {
            app('schoolManager')->setActiveSchool(null);
        } catch (\Throwable) {
            //
        }
    }
}

function docsSetSchoolContext(School $school): void
{
    if (app()->bound('schoolManager')) {
        app('schoolManager')->setActiveSchool($school);
    }
}

function docsUser(array $attrs = []): User
{
    $id = (string) Str::uuid();

    DB::table('users')->insert([
        'id' => $id,
        'name' => $attrs['name'] ?? 'Docs User',
        'email' => $attrs['email'] ?? ('docs-'.Str::random(8).'@example.test'),
        'password' => bcrypt('password'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return User::query()->without(['profile'])->findOrFail($id);
}

function docsSchool(): School
{
    $id = (string) Str::uuid();
    DB::table('schools')->insert([
        'id' => $id,
        'name' => 'Docs School',
        'code' => 'DOC',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return School::query()->findOrFail($id);
}

function docsGrantDirect(User $user, string $permission, ?string $schoolId = null): void
{
    $permissionId = DB::table('permissions')->where('name', $permission)->value('id');
    expect($permissionId)->not->toBeNull();

    DB::table('permission_user')->insert([
        'permission_id' => $permissionId,
        'user_id' => $user->getKey(),
        'user_type' => User::class,
        'school_id' => $schoolId,
    ]);
}

function docsGrantViaRole(User $user, string $permission, ?string $schoolId = null): void
{
    $roleId = (string) Str::uuid();
    DB::table('roles')->insert([
        'id' => $roleId,
        'name' => 'docs-role-'.Str::random(6),
        'display_name' => 'Docs Role',
        'disabled' => false,
        'school_id' => $schoolId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $permissionId = DB::table('permissions')->where('name', $permission)->value('id');
    DB::table('permission_role')->insert([
        'permission_id' => $permissionId,
        'role_id' => $roleId,
    ]);

    DB::table('role_user')->insert([
        'role_id' => $roleId,
        'user_id' => $user->getKey(),
        'user_type' => User::class,
        'school_id' => $schoolId,
    ]);
}

it('denies unauthenticated access to the documentation home', function () {
    $this->get(route('docent.docs.home'))
        ->assertRedirect();
});

it('allows an authenticated user to open the documentation home', function () {
    $user = docsUser();

    $this->actingAs($user)
        ->get(route('docent.docs.home'))
        ->assertOk()
        ->assertSee('School Manager Documentation', false);
});

it('renders an unrestricted documentation page for any authenticated user', function () {
    $user = docsUser();

    $this->actingAs($user)
        ->get(route('docent.docs.show', 'students/overview'))
        ->assertOk()
        ->assertSee('Students overview', false);
});

it('hides a restricted page from an unauthorized authenticated user', function () {
    $user = docsUser();

    $this->actingAs($user)
        ->get(route('docent.docs.show', 'students/administration'))
        ->assertNotFound();

    $this->docs()->as($user)->page('students/administration')->assertNotVisible();
});

it('shows a restricted page to a user with a direct permission grant', function () {
    $user = docsUser();
    docsGrantDirect($user, 'student.view');

    $this->actingAs($user)
        ->get(route('docent.docs.show', 'students/administration'))
        ->assertOk()
        ->assertSee('Student administration notes', false);

    $this->docs()->as($user)->page('students/administration')->assertVisible();
});

it('shows a restricted page when permission is role-derived', function () {
    $user = docsUser();
    docsGrantViaRole($user, 'student.view');

    $this->docs()->as($user)->page('students/administration')
        ->assertVisible()
        ->assertSee('Student administration notes');
});

it('does not leak restricted pages through search for unauthorized users', function () {
    $user = docsUser();

    $this->docs()->as($user)->search('administration')
        ->assertMissing('students/administration');
});

it('includes restricted pages in search for authorized users', function () {
    $user = docsUser();
    docsGrantDirect($user, 'student.view');

    $this->docs()->as($user)->search('administration')
        ->assertSees('students/administration');
});

it('does not leak restricted pages through navigation for unauthorized users', function () {
    $user = docsUser();

    $this->actingAs($user)
        ->get(route('docent.docs.home'))
        ->assertOk()
        ->assertDontSee('Student administration notes', false);
});

it('hides restricted conditional content from unauthorized users', function () {
    $user = docsUser();

    // Assert against rendered HTML text (not Markdown source). Bold markers
    // are not present after CommonMark render, so checking the source string
    // would pass even if the :::can block leaked.
    $this->docs()->as($user)->page('students/overview')
        ->assertVisible()
        ->assertDontSee('Administrators with')
        ->assertDontSee('can open individual profiles')
        ->assertSee('If you cannot open student profiles');
});

it('shows restricted conditional content to authorized users', function () {
    $user = docsUser();
    docsGrantDirect($user, 'student.view');

    $this->docs()->as($user)->page('students/overview')
        ->assertVisible()
        ->assertSee('Administrators with')
        ->assertDontSee('If you cannot open student profiles');
});

it('evaluates authorization under active school context for school-scoped grants', function () {
    $user = docsUser();
    $school = docsSchool();

    docsGrantDirect($user, 'student.view', (string) $school->getKey());

    docsClearSchoolContext();
    expect(app(\App\Contracts\Authorization\Authorization::class)->allows($user, 'student.view'))->toBeFalse();
    $this->docs()->as($user)->page('students/administration')->assertNotVisible();

    docsSetSchoolContext($school);
    expect(app(\App\Contracts\Authorization\Authorization::class)->allows($user, 'student.view'))->toBeTrue();
    $this->docs()->as($user)->page('students/administration')->assertVisible();
});

it('renders the full visible corpus for an authenticated user without errors', function () {
    $user = docsUser();

    $this->docs()->as($user)->assertAllPagesRender();
});

it('renders the full visible corpus for a user with student.view', function () {
    $user = docsUser();
    docsGrantDirect($user, 'student.view');

    $this->docs()->as($user)->assertAllPagesRender();
});

it('exercises AuthorizationService for documentation abilities via Gate', function () {
    $user = docsUser();
    $auth = app(\App\Contracts\Authorization\Authorization::class);

    expect($auth->allows($user, 'student.view'))->toBeFalse();

    docsGrantDirect($user, 'student.view');
    expect($auth->allows($user, 'student.view'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Gate::forUser($user)->allows('student.view'))->toBeTrue();
});

it('does not intercept non-permission dotted Gate abilities', function () {
    // Regression: Gate::before must only delegate known School Manager
    // permission identities. A dotted ability that is not in the permission
    // catalogue must reach its normal Gate definition (not AuthorizationService).
    \Illuminate\Support\Facades\Gate::define(
        'custom.dotted.non_permission',
        fn ($user) => true
    );

    $user = docsUser();

    // AuthorizationService would deny this identity (it is not a permission).
    // If the bridge intercepts it, allows() returns false and this fails.
    expect(\Illuminate\Support\Facades\Gate::forUser($user)->allows('custom.dotted.non_permission'))
        ->toBeTrue();

    // Permission identities still go through AuthorizationService.
    expect(\Illuminate\Support\Facades\Gate::forUser($user)->allows('student.view'))
        ->toBeFalse();

    docsGrantDirect($user, 'student.view');
    expect(\Illuminate\Support\Facades\Gate::forUser($user)->allows('student.view'))
        ->toBeTrue();
});
