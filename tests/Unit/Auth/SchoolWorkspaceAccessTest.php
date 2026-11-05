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

        if (! Schema::hasTable('school_sections')) {
            Schema::create('school_sections', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('school_id');
                $table->string('name')->nullable();
                $table->timestamps();
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

    public function test_non_member_cannot_select_even_when_session_points_at_school(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $school = School::query()->create(['name' => 'Gamma']);

        $this->actingAs($user);
        session(['active_school_id' => $school->id]);

        $service = app(SchoolService::class);
        $this->assertNull($service->getActiveSchool());
        $this->assertNull(session('active_school_id'));
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


    public function test_section_rejected_without_active_school(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $school = School::query()->create(['name' => 'SecSchool']);
        $section = \App\Models\SchoolSection::query()->create([
            'school_id' => $school->id,
            'name' => 'A',
        ]);

        $this->actingAs($user);
        $service = app(SchoolService::class);

        $this->expectException(\Exception::class);
        $service->setActiveSection($section);
    }

    public function test_switching_school_clears_section_context(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $schoolA = School::query()->create(['name' => 'SchoolA']);
        $schoolB = School::query()->create(['name' => 'SchoolB']);
        $user->schools()->attach([$schoolA->id, $schoolB->id]);
        $sectionA = \App\Models\SchoolSection::query()->create([
            'school_id' => $schoolA->id,
            'name' => 'SectionA',
        ]);

        $this->actingAs($user);
        $service = app(SchoolService::class);
        $service->setActiveSchool($schoolA);
        $service->setActiveSection($sectionA);
        $this->assertNotNull($service->getActiveSection());

        $service->setActiveSchool($schoolB);
        $this->assertNull(session('active_section_id'));
        $this->assertNull($service->getActiveSection());
    }

    public function test_get_active_section_rejects_section_from_other_school(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $schoolA = School::query()->create(['name' => 'SchoolA2']);
        $schoolB = School::query()->create(['name' => 'SchoolB2']);
        $user->schools()->attach($schoolA->id);
        $sectionB = \App\Models\SchoolSection::query()->create([
            'school_id' => $schoolB->id,
            'name' => 'Foreign',
        ]);

        $this->actingAs($user);
        $service = app(SchoolService::class);
        $service->setActiveSchool($schoolA);
        session(['active_section_id' => $sectionB->id]);

        $this->assertNull($service->getActiveSection());
    }

    public function test_fresh_instance_clears_section_when_session_school_changes(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $schoolA = School::query()->create(['name' => 'SessA']);
        $schoolB = School::query()->create(['name' => 'SessB']);
        $user->schools()->attach([$schoolA->id, $schoolB->id]);

        if (! Schema::hasTable('school_sections')) {
            Schema::create('school_sections', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('school_id');
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        $sectionA = \App\Models\SchoolSection::query()->create([
            'school_id' => $schoolA->id,
            'name' => 'SecA',
        ]);

        $this->actingAs($user);
        session([
            'active_school_id' => $schoolA->id,
            'active_section_id' => $sectionA->id,
        ]);

        $service = app()->make(SchoolService::class);
        $service->setActiveSchool($schoolB);

        $this->assertNull(session('active_section_id'));
        $this->assertNull($service->getActiveSection());
    }
}
