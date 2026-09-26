<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Services\Almacenes\AlcanceAlmacenesService;
use App\Services\Almacenes\AplicarLoteImportacionAlmacenService;
use App\Services\Almacenes\CrearLoteImportacionAlmacenService;
use App\Services\Almacenes\SimularImportacionAlmacenService;
use App\Support\Almacenes\OperacionesImportacionAlmacen;
use App\Support\Almacenes\ReglasFichaProductoImportacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportacionLoteController extends Controller
{
    public function index(Request $request, AlcanceAlmacenesService $alcance): Response
    {
        $this->autorizarVer();
        $user = $request->user();

        $query = ImportacionAlmacenLog::query()->with(['almacen.sucursal'])->latest()->limit(50);
        if (! $user->can('catalogos.gestionar')) {
            $query->where('user_id', $user->id);
        }
        $lotes = $query->get()
            ->map(fn (ImportacionAlmacenLog $l) => $this->loteParaLista($l));

        $sucursales = $alcance->sucursalesOperables($user);
        $sucursalActiva = $alcance->sucursalActivaId($user);

        return Inertia::render('Almacenes/Importaciones/Index', [
            'lotes' => $lotes,
            'operacionesDisponibles' => $this->operacionesParaUi($user),
            'sucursales' => $sucursales,
            'almacenes' => $alcance->almacenesOperables($user),
            'sucursal_activa_id' => $sucursalActiva,
            'preset' => $request->query('preset'),
            'plantillas' => $this->plantillasPorPreset(),
            'sin_sucursales_operables' => $sucursales->isEmpty(),
        ]);
    }

    public function analizar(Request $request, CrearLoteImportacionAlmacenService $crear, AlcanceAlmacenesService $alcance): JsonResponse
    {
        $this->autorizarImportar();
        $user = $request->user();

        $validated = $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls',
            'operaciones' => 'required|array|min:1',
            'operaciones.*' => 'string|in:'.implode(',', OperacionesImportacionAlmacen::todas()),
            'almacen_id' => 'nullable|exists:almacenes,id',
            'sucursal_id' => 'nullable|integer|exists:sucursales,id',
        ]);

        if (isset($validated['almacen_id'])) {
            $alcance->asegurarAlmacenOperable($user, (int) $validated['almacen_id']);
        }
        if (isset($validated['sucursal_id'])) {
            $alcance->asegurarSucursalOperable($user, (int) $validated['sucursal_id']);
        }

        $resultado = $crear->analizar(
            (int) $user->id,
            $request->file('archivo'),
            $validated['operaciones'],
            isset($validated['almacen_id']) ? (int) $validated['almacen_id'] : null,
        );

        return response()->json([
            'lote_id' => $resultado['lote']->id,
            'headers' => $resultado['headers'],
            'redirect' => route('almacenes.importaciones.show', $resultado['lote']->id),
        ]);
    }

    public function show(Request $request, ImportacionAlmacenLog $lote, AlcanceAlmacenesService $alcance): Response
    {
        $this->autorizarVerLote($lote, $request->user());
        $lote->load(['almacen.sucursal']);

        return Inertia::render('Almacenes/Importaciones/Show', [
            'lote' => $this->loteDetalle($lote),
            'operacionesLabels' => OperacionesImportacionAlmacen::etiquetasParaUi(),
        ]);
    }

    public function simular(Request $request, ImportacionAlmacenLog $lote, SimularImportacionAlmacenService $simular): JsonResponse
    {
        $this->autorizarVerLote($lote, $request->user());

        $request->validate([
            'mapping' => 'required|array',
            'mapping.sku' => 'required|string',
        ]);
        $mapping = $request->input('mapping', []);
        if (! is_array($mapping)) {
            $mapping = [];
        }
        $this->validarMappingHubFichaProducto($lote, $mapping);

        $lote->update(['mapping' => $mapping]);
        $resumen = $simular->ejecutar($lote->fresh());

        return response()->json([
            'success' => true,
            'resumen' => $resumen,
            'lote' => $this->loteDetalle($lote->fresh()->load(['almacen.sucursal'])),
        ]);
    }

    public function aplicar(
        Request $request,
        ImportacionAlmacenLog $lote,
        AplicarLoteImportacionAlmacenService $aplicar,
        AlcanceAlmacenesService $alcance,
    ): JsonResponse {
        $request->validate([
            'mapping' => 'required|array',
            'mapping.sku' => 'required|string',
        ]);
        $mapping = $request->input('mapping', []);
        if (! is_array($mapping)) {
            $mapping = [];
        }
        $this->validarMappingHubFichaProducto($lote, $mapping);

        if ($lote->almacen_id) {
            $alcance->asegurarAlmacenOperable($request->user(), (int) $lote->almacen_id);
        }

        $resultado = $aplicar->ejecutar($lote, $request->user(), $mapping);

        return response()->json(array_merge(['success' => true], $resultado));
    }

    public function errores(Request $request, ImportacionAlmacenLog $lote): StreamedResponse
    {
        $this->autorizarVerLote($lote, $request->user());

        if ($lote->reporte_errores_token) {
            return app(ImportacionAlmacenController::class)
                ->descargarReporteErrores($lote->reporte_errores_token);
        }

        $errores = $lote->errores()->orderBy('fila')->get();

        return response()->streamDownload(function () use ($errores) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['fila', 'referencia', 'campo', 'mensaje']);
            foreach ($errores as $e) {
                fputcsv($out, [$e->fila, $e->referencia, $e->campo, $e->mensaje]);
            }
            fclose($out);
        }, "errores_lote_{$lote->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function operacionesParaUi($user): array
    {
        $out = [];
        foreach (OperacionesImportacionAlmacen::todas() as $op) {
            $permiso = OperacionesImportacionAlmacen::permisoParaOperacion($op);
            $puede = $user->can('catalogos.gestionar')
                || ($permiso && $user->can($permiso))
                || ($op === OperacionesImportacionAlmacen::FICHA_PRODUCTO && ($user->can('gestion_interna.productos.gestionar') || $user->can('almacenes.productos.gestionar')));
            if ($puede) {
                $out[] = [
                    'id' => $op,
                    'label' => OperacionesImportacionAlmacen::etiqueta($op),
                    'descripcion' => OperacionesImportacionAlmacen::descripcionCorta($op),
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function plantillasPorPreset(): array
    {
        return [
            'productos' => route('gestion_interna.productos.plantilla_importacion'),
            'inventario' => route('almacenes.inventarios.plantilla_importacion'),
            'costos' => route('almacenes.costos.plantilla_importacion'),
            'existencias' => route('almacenes.inventarios.plantilla_importacion'),
        ];
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function validarMappingHubFichaProducto(ImportacionAlmacenLog $lote, array $mapping): void
    {
        $operaciones = $lote->operaciones ?? [];
        if (! in_array(OperacionesImportacionAlmacen::FICHA_PRODUCTO, $operaciones, true)) {
            return;
        }

        ReglasFichaProductoImportacion::asegurarColumnasMapeadas($mapping);
    }

    private function autorizarVer(): void
    {
        $user = request()->user();
        if ($user->can('catalogos.gestionar')
            || $user->can('gestion_interna.productos.importar')
            || $user->can('gestion_interna.productos.gestionar')
            || $user->can('almacenes.inventarios.importar')
            || $user->can('almacenes.costos.importar')) {
            return;
        }
        abort(403);
    }

    private function autorizarImportar(): void
    {
        $this->autorizarVer();
    }

    private function autorizarVerLote(ImportacionAlmacenLog $lote, $user): void
    {
        $this->autorizarVer();
        if ($lote->user_id !== $user->id && ! $user->can('catalogos.gestionar')) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loteDetalle(ImportacionAlmacenLog $lote): array
    {
        return array_merge($lote->toArray(), [
            'etiqueta_tipo' => $lote->etiquetaTipo(),
            'resumen' => $lote->resumenParaUi(),
            'resumen_simulacion' => $lote->resumen_simulacion,
            'operaciones' => $lote->operaciones ?? [],
            'headers' => $lote->payload['headers'] ?? [],
            'almacen' => $lote->almacen ? [
                'id' => $lote->almacen->id,
                'codigo' => $lote->almacen->codigo,
                'nombre' => $lote->almacen->nombre,
                'sucursal' => $lote->almacen->sucursal ? [
                    'id' => $lote->almacen->sucursal->id,
                    'nombre' => $lote->almacen->sucursal->nombre,
                ] : null,
            ] : null,
            'reporte_errores_url' => $lote->reporte_errores_token
                ? route('almacenes.importaciones.reporte_errores', ['token' => $lote->reporte_errores_token])
                : route('almacenes.importaciones.errores', $lote->id),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loteParaLista(ImportacionAlmacenLog $lote): array
    {
        return [
            'id' => $lote->id,
            'estado' => $lote->estado,
            'tipo' => $lote->tipo,
            'etiqueta_tipo' => $lote->etiquetaTipo(),
            'created_at' => $lote->created_at?->toIso8601String(),
            'total_filas' => $lote->total_filas,
            'procesados' => $lote->procesados,
            'almacen' => $lote->almacen ? [
                'codigo' => $lote->almacen->codigo,
                'nombre' => $lote->almacen->nombre,
                'sucursal_nombre' => $lote->almacen->sucursal?->nombre,
            ] : null,
        ];
    }
}
