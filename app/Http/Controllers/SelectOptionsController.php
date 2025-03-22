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
                $model = app(\App\Models\Student::class);
                break;

            default:
                return response()->json(['error' => 'Invalid resource'], 500);
        }

        if (!isset($model)) {
            return response()->json(['error' => 'Model not found'], 504);
        }

        return response()->json($model->paginate(50, ['id', 'name']), 200);
    }
}
