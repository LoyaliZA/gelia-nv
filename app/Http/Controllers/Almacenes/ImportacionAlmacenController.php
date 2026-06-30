<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportacionAlmacenController extends Controller
{
    public function descargarReporteErrores(string $token): BinaryFileResponse
    {
        $user = request()->user();

        if (! $user->can('catalogos.gestionar')
            && ! $user->can('almacenes.productos.gestionar')
            && ! $user->can('almacenes.inventarios.importar')) {
            abort(403);
        }

        $path = Storage::path('temp/import_errores_' . preg_replace('/[^a-f0-9\-]/i', '', $token) . '.csv');

        if (! file_exists($path)) {
            abort(404, 'Reporte no encontrado o expirado.');
        }

        return response()->download($path, 'reporte_errores_importacion.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend(true);
    }
}
