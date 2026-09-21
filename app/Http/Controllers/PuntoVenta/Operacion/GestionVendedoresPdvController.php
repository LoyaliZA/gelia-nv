<?php

namespace App\Http\Controllers\PuntoVenta\Operacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class GestionVendedoresPdvController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('punto_venta.operacion.index');
    }

    public function datos(): RedirectResponse
    {
        return redirect()->route('punto_venta.operacion.datos');
    }
}
