<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CambioListaImportacionCliente;
use App\Models\ErroresImportacionCliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\User;
use App\Services\Clientes\RegistrarHistorialMontoClienteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditoriaMontosClienteController extends Controller
{
    public function importaciones(Request $request)
    {
        $importaciones = ImportacionCliente::with('usuario:id,name,apellido_paterno')
            ->latest()
            ->paginate(15, ['*'], 'importaciones_page')
            ->withQueryString();

        return response()->json($importaciones);
    }

    public function auditoriaMontos(Request $request)
    {
        $auditoriaMontos = $this->queryAuditoriaMontos($request, excludeCargaMasiva: true)
            ->paginate(25, ['*'], 'auditoria_page')
            ->withQueryString();

        return response()->json($auditoriaMontos);
    }

    public function datosAuditoria(Request $request)
    {
        $importaciones = ImportacionCliente::with('usuario:id,name,apellido_paterno')
            ->latest()
            ->paginate(15, ['*'], 'importaciones_page')
            ->withQueryString();

        $auditoriaMontos = $this->queryAuditoriaMontos($request, excludeCargaMasiva: true)
            ->paginate(25, ['*'], 'auditoria_page')
            ->withQueryString();

        $usuariosFiltro = User::orderBy('name')->get(['id', 'name', 'apellido_paterno']);

        return response()->json([
            'importaciones' => $importaciones,
            'auditoriaMontos' => $auditoriaMontos,
            'usuariosFiltro' => $usuariosFiltro,
            'filtrosAuditoria' => $request->only(['q_auditoria', 'origen', 'usuario_id', 'fecha_inicio', 'fecha_fin']),
        ]);
    }

    public function auditoriaImportacion(ImportacionCliente $importacion, Request $request)
    {
        $importacion->load('usuario:id,name,apellido_paterno');

        $cambiosMontos = HistorialMontoCliente::with([
            'cliente:id,numero_cliente,nombre',
            'usuario:id,name,apellido_paterno',
        ])
            ->where('importacion_cliente_id', $importacion->id)
            ->latest()
            ->paginate(25, ['*'], 'cambios_page')
            ->withQueryString();

        $errores = ErroresImportacionCliente::where('importacion_cliente_id', $importacion->id)
            ->latest()
            ->paginate(25, ['*'], 'errores_page')
            ->withQueryString();

        $cambiosLista = CambioListaImportacionCliente::where('importacion_cliente_id', $importacion->id)
            ->latest()
            ->paginate(25, ['*'], 'listas_page')
            ->withQueryString();

        return response()->json([
            'importacion' => $importacion,
            'cambiosMontos' => $cambiosMontos,
            'cambiosLista' => $cambiosLista,
            'errores' => $errores,
            'erroresDetalleDisponible' => $errores->total() > 0 || $importacion->errores === 0,
            'cambiosListaDisponible' => $cambiosLista->total() > 0
                || ($importacion->ascensos + ($importacion->descensos ?? 0)) === 0,
        ]);
    }

    public function descargarArchivo(ImportacionCliente $importacion): StreamedResponse
    {
        Gate::authorize('clientes.carga_masiva');

        if (! Storage::disk('local')->exists($importacion->ruta_almacenamiento)) {
            abort(404, 'Archivo de importación no encontrado.');
        }

        return Storage::disk('local')->download(
            $importacion->ruta_almacenamiento,
            $importacion->nombre_archivo_original,
        );
    }

    private function queryAuditoriaMontos(Request $request, bool $excludeCargaMasiva = false, ?int $importacionId = null): Builder
    {
        $query = HistorialMontoCliente::with([
            'cliente:id,numero_cliente,nombre',
            'usuario:id,name,apellido_paterno',
            'importacion:id,nombre_archivo_original,created_at',
            'solicitud:id,monto_cotizado,vendedor_id',
            'solicitud.vendedor:id,name,apellido_paterno',
        ])->latest();

        if ($excludeCargaMasiva) {
            $query->where('origen', '!=', RegistrarHistorialMontoClienteService::ORIGEN_CARGA_MASIVA);
        }

        if ($importacionId !== null) {
            $query->where('importacion_cliente_id', $importacionId);
        } elseif ($request->filled('importacion_id')) {
            $query->where('importacion_cliente_id', $request->integer('importacion_id'));
        }

        $terminoBusqueda = $request->filled('q_auditoria')
            ? trim($request->q_auditoria)
            : ($request->filled('q') ? trim($request->q) : null);

        if ($terminoBusqueda !== null && $terminoBusqueda !== '') {
            $query->whereHas('cliente', function ($sub) use ($terminoBusqueda) {
                $sub->where('numero_cliente', 'like', "%{$terminoBusqueda}%")
                    ->orWhere('nombre', 'like', "%{$terminoBusqueda}%");
            });
        }

        if ($request->filled('origen')) {
            $query->where('origen', $request->origen);
        }

        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->integer('usuario_id'));
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        return $query;
    }
}
