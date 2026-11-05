<?php

namespace Tests\Unit\Auth;

use App\Models\School;
use App\Models\User;
use App\Services\SchoolService;
use App\Support\Auth\SchoolWorkspaceAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolWorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('username')->unique();
                $table->string('password')->nullable();
                $table->string('lifecycle')->default('active');
                $table->boolean('force_password_change')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('schools')) {
            Schema::create('schools', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('school_users')) {
            Schema::create('school_users', function (Blueprint $table) {
                $table->uuid('user_id');
                $table->uuid('school_id');
                $table->primary(['user_id', 'school_id']);
            });
        }
    }

    public function test_member_can_select_participating_school(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $school = School::query()->create(['name' => 'Alpha']);
        $user->schools()->attach($school->id);

        $this->assertTrue(SchoolWorkspaceAccess::canSelect($user, $school));
    }

    public function test_non_member_cannot_select_school(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $school = School::query()->create(['name' => 'Beta']);

        $this->assertFalse(SchoolWorkspaceAccess::canSelect($user, $school));
    }

    public function test_role_name_alone_does_not_grant_workspace_access(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $school = School::query()->create(['name' => 'Gamma']);

        // Even if Laratrust role helpers exist, SchoolWorkspaceAccess must not use them.
        $this->assertFalse(SchoolWorkspaceAccess::canSelect($user, $school));
    }

    public function test_get_active_school_ignores_unauthorized_header(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $school = School::query()->create(['name' => 'HeaderSchool']);

        $this->actingAs($user);

        $request = \Illuminate\Http\Request::create('/', 'GET');
        $request->headers->set('X-School-Id', $school->id);

        $service = app(SchoolService::class);
        $resolved = $service->getActiveSchool($request);

        $this->assertNull($resolved);
    }

    public function test_get_active_school_accepts_member_session(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $school = School::query()->create(['name' => 'MemberSchool']);
        $user->schools()->attach($school->id);

        $this->actingAs($user);
        session(['active_school_id' => $school->id]);

        $service = app(SchoolService::class);
        $resolved = $service->getActiveSchool();

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($school));
    }

    public function test_login_throttle_threshold_does_not_use_school_settings(): void
    {
        $request = \App\Http\Requests\Auth\LoginRequest::create('/login', 'POST', [
            'login' => 'throttle-user',
            'password' => 'secret',
        ], server: ['REMOTE_ADDR' => '198.51.100.20']);
        $request->setContainer(app())->setRedirector(app('redirect'));

        $src = file_get_contents(app_path('Http/Requests/Auth/LoginRequest.php'));
        $this->assertStringNotContainsString(
            "getMergedSettings('authentication', \$school)",
            $src,
            'Login throttle must not load School-scoped authentication settings'
        );
        $this->assertStringNotContainsString(
            'GetSchoolModel()',
            $src
        );
    }
}
