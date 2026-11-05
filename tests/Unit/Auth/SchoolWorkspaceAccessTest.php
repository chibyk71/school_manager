<?php

namespace Tests\Unit\Auth;

use App\Models\School;
use App\Models\User;
use App\Support\Auth\SchoolWorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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

    public function test_non_member_without_tenant_authority_cannot_select_school(): void
    {
        $user = User::factory()->create(['lifecycle' => 'active']);
        $school = School::query()->create(['name' => 'Beta']);

        $this->assertFalse(SchoolWorkspaceAccess::canSelect($user, $school));
    }
}
