<?php

namespace App\Http\Controllers\Herramientas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoPorcentajeEscalonamientoLista;

class EjercicioEscalonamientoController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('EjercicioEscalonamiento/Index', [
            'listas' => CatalogoListaDescuento::where('activo', true)
                ->orderBy('monto_requerido', 'asc')
                ->get(),
            'porcentajes' => CatalogoPorcentajeEscalonamientoLista::all()
        ]);
    }
}
