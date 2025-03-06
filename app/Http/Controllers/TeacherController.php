<?php

namespace App\Http\Controllers;

use App\Models\Employee\Staff;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeacherController extends Controller
{
    public function index()  {
        // $teachers = Staff::where('role','teacher')->paginate();
        return Inertia::render('UserManagement/Teacher');
    }


}
