<?php

namespace App\Http\Controllers\Soporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Inertia\Inertia;

class SoporteBaseConocimientoController extends Controller
{
    public function index()
    {
        return Inertia::render('Soporte/QA/Index');
    }
    
    // ... otros metodos como sugerencias
}
