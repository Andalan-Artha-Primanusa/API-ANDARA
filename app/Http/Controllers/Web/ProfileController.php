<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class ProfileController extends Controller
{
    public function index()
    {
        return view('employee.profile');
    }
}