<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FilaRevisionImportacionCliente;
use App\Models\ImportacionCliente;
use App\Services\Clientes\PresentarSeguimientoImportacionCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ImportacionClienteSeguimientoController extends Controller
{
    public function activas(Request $request)
    {
        Gate::authorize('clientes.carga_masiva');

        $ids = ImportacionCliente::query()
            ->where('usuario_id', $request->user()->id)
            ->whereIn('estado', ['recibido', 'analizando', 'previsualizada', 'en_cola', 'en_curso'])
            ->latest('id')
            ->limit(10)
            ->pluck('id');

        return response()->json(['ids' => $ids]);
    }

    public function progreso(Request $request, ImportacionCliente $importacion, PresentarSeguimientoImportacionCliente $presentar)
    {
        $this->autorizar($request, $importacion);

        return response()->json($presentar->progreso($importacion));
    }

    public function revision(Request $request, ImportacionCliente $importacion, PresentarSeguimientoImportacionCliente $presentar)
    {
        $this->autorizar($request, $importacion);
        $q = trim((string) $request->query('q', ''));
        $categoria = (string) $request->query('categoria', '');
        $filas = FilaRevisionImportacionCliente::query()
            ->where('importacion_cliente_id', $importacion->id)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('numero_cliente', 'like', '%'.$q.'%')
                        ->orWhere('nombre', 'like', '%'.$q.'%')
                        ->orWhere('motivo', 'like', '%'.$q.'%');
                });
            })
            ->when($categoria !== '', fn ($query) => $query->where('categoria', $categoria))
            ->when($request->boolean('incidencias'), function ($query) {
                $query->where(function ($inner) {
                    $inner->where('es_bloqueo', true)->orWhere('es_aviso', true)->orWhere('categoria', 'error');
                });
            })
            ->orderBy('numero_fila')
            ->paginate(25)
            ->withQueryString();

        return response()->json([
            'progreso' => $presentar->progreso($importacion),
            'filas' => $filas,
        ]);
    }

    private function autorizar(Request $request, ImportacionCliente $importacion): void
    {
        Gate::authorize('clientes.carga_masiva');
        if ((int) $importacion->usuario_id !== (int) $request->user()->id) {
            abort(404);
        }
    }
}
