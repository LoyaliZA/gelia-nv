<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class PruebasComponentesController extends Controller
{
    /** Banco de pruebas visuales: no escribe datos ni recarga la aplicación. */
    public function index(): Response
    {
        return Inertia::render('Admin/PruebasComponentes');
    }
}
