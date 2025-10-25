<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the SchoolService.
 *
 * @method static void setActiveSchool(\App\Models\School $school)
 * @method static \App\Models\School|null getActiveSchool(?\Illuminate\Http\Request $request = null)
 * @method static \App\Models\Branch|null getActiveBranch(?\Illuminate\Http\Request $request = null)
 * @method static \App\Models\School createSchool(array $data)
 * @method static \App\Models\Branch createBranch(array $data, \App\Models\School $school)
 * @method static \App\Models\User assignAdmin(array $userData, \App\Models\School $school, ?\App\Models\Branch $branch = null)
 */
class SchoolManager extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'schoolManager';
    }
}
