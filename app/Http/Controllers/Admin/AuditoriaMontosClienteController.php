<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use App\Models\User;
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
        $query = HistorialMontoCliente::with([
            'cliente:id,numero_cliente,nombre',
            'usuario:id,name,apellido_paterno',
            'importacion:id,nombre_archivo_original,created_at',
            'solicitud:id,monto_cotizado,vendedor_id',
            'solicitud.vendedor:id,name,apellido_paterno',
        ])->latest();

        if ($request->filled('q')) {
            $termino = trim($request->q);
            $query->whereHas('cliente', function ($sub) use ($termino) {
                $sub->where('numero_cliente', 'like', "%{$termino}%")
                    ->orWhere('nombre', 'like', "%{$termino}%");
            });
        }

        if ($request->filled('origen')) {
            $query->where('origen', $request->origen);
        }

        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->integer('usuario_id'));
        }

        if ($request->filled('importacion_id')) {
            $query->where('importacion_cliente_id', $request->integer('importacion_id'));
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        $auditoriaMontos = $query->paginate(25, ['*'], 'auditoria_page')->withQueryString();

        return response()->json($auditoriaMontos);
    }

    public function datosAuditoria(Request $request)
    {
        $importaciones = ImportacionCliente::with('usuario:id,name,apellido_paterno')
            ->latest()
            ->paginate(15, ['*'], 'importaciones_page')
            ->withQueryString();

        $query = HistorialMontoCliente::with([
            'cliente:id,numero_cliente,nombre',
            'usuario:id,name,apellido_paterno',
            'importacion:id,nombre_archivo_original,created_at',
            'solicitud:id,monto_cotizado,vendedor_id',
            'solicitud.vendedor:id,name,apellido_paterno',
        ])->latest();

        if ($request->filled('q_auditoria')) {
            $termino = trim($request->q_auditoria);
            $query->whereHas('cliente', function ($sub) use ($termino) {
                $sub->where('numero_cliente', 'like', "%{$termino}%")
                    ->orWhere('nombre', 'like', "%{$termino}%");
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

        $auditoriaMontos = $query->paginate(25, ['*'], 'auditoria_page')->withQueryString();

        $usuariosFiltro = User::orderBy('name')->get(['id', 'name', 'apellido_paterno']);

        return response()->json([
            'importaciones' => $importaciones,
            'auditoriaMontos' => $auditoriaMontos,
            'usuariosFiltro' => $usuariosFiltro,
            'filtrosAuditoria' => $request->only(['q_auditoria', 'origen', 'usuario_id', 'fecha_inicio', 'fecha_fin']),
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
}
