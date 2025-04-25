<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SelectOptionsController extends Controller
{
    public function __invoke(Request $request)
    {
        $id = $request->resource;

        switch ($id) {
            case 'school-section':
                $model = app(\App\Models\SchoolSection::class);
                break;
            case 'class-level':
                $model = app(\App\Models\Academic\ClassLevel::class);
                break;
            case 'class-section':
                $model = app(\App\Models\Academic\ClassSection::class);
                break;
            case 'school':
                $model = app(\App\Models\School::class);
                break;
            case 'subject':
                $model = app(\App\Models\Academic\Subject::class);
                break;
            case 'teacher':
                $model = app(\App\Models\Employee\Staff::class);
                break;
            case 'student':
                $model = app(\App\Models\Academic\Student::class);
                break;
            case 'role':
                $model = app(\App\Models\Role::class);
                break;
            case 'department':
                $model = app(\App\Models\Employee\Department::class);
                break;

            default:
                return response()->json(['error' => 'Invalid resource'], 500);
        }

        if (!isset($model)) {
            return response()->json(['error' => 'Model not found'], 504);
        }
        $data = $model->get(['id', 'name', 'display_name']);
        $data->transform(function ($item) {
            $item->name = !empty($item->display_name) ? $item->display_name : $item->name;
            unset($item->display_name);
            return $item;
        });

        return response()->json(['data' => $data], 200);
    }
}
