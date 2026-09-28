<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PrivacidadAppController extends Controller
{
    public function show(): View
    {
        return view('privacidad-app');
    }
}
