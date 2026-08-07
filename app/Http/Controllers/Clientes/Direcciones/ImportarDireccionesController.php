<?php

namespace App\Http\Controllers\Clientes\Direcciones;

use App\Http\Controllers\Controller;
use App\Services\Clientes\Direcciones\ImportarDireccionesClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportarDireccionesController extends Controller
{
    public function plantilla(ImportarDireccionesClienteService $importador): StreamedResponse
    {
        Gate::authorize('clientes.direcciones.crear');

        return $importador->descargarPlantilla();
    }

    public function importar(Request $request, ImportarDireccionesClienteService $importador): RedirectResponse
    {
        Gate::authorize('clientes.direcciones.crear');

        $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls,txt',
        ]);

        $stats = $importador->ejecutar($request->file('archivo'), $request->user()?->id);

        $mensaje = sprintf(
            'Importación de direcciones: %d nuevas, %d omitidas.',
            $stats['importados'],
            $stats['omitidos'],
        );

        if (! empty($stats['reporte_url'])) {
            $mensaje .= ' Descarga el reporte para revisar avisos u omitidos.';
        }

        return back()
            ->with('success', $mensaje)
            ->with('reporte_importacion_almacenes', $stats);
    }
}
