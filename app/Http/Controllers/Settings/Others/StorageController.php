<?php

namespace App\Http\Controllers\Settings\Others;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorageController extends Controller
{
    public function index(){
        $settings = getMergedSettings('storage', GetSchoolModel());

        return Inertia::render('Settings/Others/Storage');
    }

    public function store(Request $request){

    }
}
