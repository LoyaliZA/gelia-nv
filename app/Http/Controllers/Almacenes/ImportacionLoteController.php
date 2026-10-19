<?php

namespace App\Http\Controllers\Almacenes;

use App\Http\Controllers\Controller;
use App\Models\Almacenes\ImportacionAlmacenLog;
use App\Services\Almacenes\AlcanceAlmacenesService;
use App\Services\Almacenes\AplicarLoteImportacionAlmacenService;
use App\Services\Almacenes\CrearLoteImportacionAlmacenService;
use App\Services\Almacenes\SimularImportacionAlmacenService;
use App\Services\Catalogos\PlantillaImportacionCatalogoService;
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

        $casos = $this->casosParaUi($user);

        return Inertia::render('Almacenes/Importaciones/Index', [
            'lotes' => $lotes,
            'casos' => $casos,
            'caso_inicial' => $this->casoInicial($request->query('preset'), $casos),
            'sucursales' => $sucursales,
            'almacenes' => $alcance->almacenesOperables($user),
            'sucursal_activa_id' => $sucursalActiva,
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
            'almacen_id' => 'required|exists:almacenes,id',
            'sucursal_id' => 'nullable|integer|exists:sucursales,id',
        ]);

        foreach ($validated['operaciones'] as $operacion) {
            if (! $this->puedeOperacion($user, $operacion)) {
                abort(403);
            }
        }

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

        $mapping = $this->mappingValidado($request, $lote);

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
        $mapping = $this->mappingValidado($request, $lote);

        if ($lote->almacen_id) {
            $alcance->asegurarAlmacenOperable($request->user(), (int) $lote->almacen_id);
        }

        $resultado = $aplicar->ejecutar($lote, $request->user(), $mapping);

        return response()->json(array_merge(['success' => true], $resultado));
    }

    public function plantilla(string $caso, PlantillaImportacionCatalogoService $plantillas): StreamedResponse
    {
        $this->autorizarVer();
        $definicion = OperacionesImportacionAlmacen::definicionCasos()[$caso] ?? null;
        if ($definicion === null) {
            abort(404);
        }

        foreach ($definicion['operaciones'] as $operacion) {
            if (! $this->puedeOperacion(request()->user(), $operacion)) {
                abort(403);
            }
        }

        return $plantillas->descargar($definicion['plantilla']);
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
    private function casosParaUi($user): array
    {
        $out = [];
        foreach (OperacionesImportacionAlmacen::definicionCasos() as $id => $caso) {
            $permitido = true;
            foreach ($caso['operaciones'] as $operacion) {
                if (! $this->puedeOperacion($user, $operacion)) {
                    $permitido = false;
                    break;
                }
            }
            if (! $permitido) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'titulo' => $caso['titulo'],
                'frase' => $caso['frase'],
                'columnas' => $caso['columnas'],
                'operaciones' => $caso['operaciones'],
                'plantilla' => route('almacenes.importaciones.plantilla', ['caso' => $id]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $casos
     */
    private function casoInicial(mixed $preset, array $casos): ?string
    {
        $id = match ((string) $preset) {
            'productos', 'asignacion' => 'productos',
            'costos' => 'costos',
            'existencias' => 'existencias',
            'inventario', 'completa' => 'completa',
            default => null,
        };
        $ids = array_column($casos, 'id');
        if ($id && in_array($id, $ids, true)) {
            return $id;
        }

        return $ids[0] ?? null;
    }

    private function puedeOperacion($user, string $operacion): bool
    {
        if ($user->can('catalogos.gestionar')) {
            return true;
        }

        $permiso = OperacionesImportacionAlmacen::permisoParaOperacion($operacion);
        if ($permiso && $user->can($permiso)) {
            return true;
        }

        return $operacion === OperacionesImportacionAlmacen::FICHA_PRODUCTO
            && ($user->can('gestion_interna.productos.gestionar') || $user->can('almacenes.productos.gestionar'));
    }

    /**
     * @return array<string, mixed>
     */
    private function mappingValidado(Request $request, ImportacionAlmacenLog $lote): array
    {
        $request->validate([
            'mapping' => 'required|array',
            'mapping.folio' => 'required|string',
        ]);
        $mapping = $request->input('mapping', []);
        if (! is_array($mapping)) {
            $mapping = [];
        }

        $operaciones = $lote->operaciones ?? [];
        if (in_array(OperacionesImportacionAlmacen::FICHA_PRODUCTO, $operaciones, true)) {
            ReglasFichaProductoImportacion::asegurarColumnasMapeadas($mapping);
        }
        if (in_array(OperacionesImportacionAlmacen::CANTIDADES_REFERENCIA, $operaciones, true) && empty($mapping['existencia'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'mapping' => 'Mapea la columna de existencia.',
            ]);
        }
        if (in_array(OperacionesImportacionAlmacen::COSTOS_PRECIOS, $operaciones, true)) {
            $tieneCosto = ! empty($mapping['costo']) || ! empty($mapping['costo_reposicion']) || ! empty($mapping['precio_venta']);
            if (! $tieneCosto) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'mapping' => 'Mapea al menos costo, costo de reposición o precio de referencia.',
                ]);
            }
        }

        return $mapping;
    }

    private function etiquetaLote(ImportacionAlmacenLog $lote): string
    {
        $caso = OperacionesImportacionAlmacen::casoDesdeOperaciones($lote->operaciones ?? []);

        return $caso ? OperacionesImportacionAlmacen::tituloCaso($caso) : $lote->etiquetaTipo();
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
            'etiqueta_tipo' => $this->etiquetaLote($lote),
            'resumen' => $lote->resumenParaUi(),
            'resumen_simulacion' => $lote->resumen_simulacion,
            'operaciones' => $lote->operaciones ?? [],
            'caso' => OperacionesImportacionAlmacen::casoDesdeOperaciones($lote->operaciones ?? []),
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
            'etiqueta_tipo' => $this->etiquetaLote($lote),
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
