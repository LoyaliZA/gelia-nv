<?php

namespace App\Http\Controllers\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubePrecioCsvException;
use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tiendanube\GenerarTiendanubePrecioCsvRequest;
use App\Http\Requests\Tiendanube\ValidarTiendanubePrecioCsvPerfilRequest;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioSeleccion;
use App\Services\Tiendanube\Precios\Exportacion\ExportarPreciosCsvService;
use App\Services\Tiendanube\Precios\Exportacion\ExportarReporteRevisionCsvService;
use App\Services\Tiendanube\Precios\Exportacion\TiendanubePrecioCsvPerfilService;
use App\Support\Tiendanube\Precios\TiendanubePrecioCsvColumnaCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TiendanubePrecioCsvController extends Controller
{
    public function __construct(
        private readonly TiendanubePrecioCsvPerfilService $perfiles,
        private readonly ExportarPreciosCsvService $exportar,
        private readonly ExportarReporteRevisionCsvService $reporte,
    ) {}

    public function perfil(Request $request): JsonResponse
    {
        $this->authorize('tiendanube.ver');

        return response()->json($this->perfiles->consultar($this->storeIdOpcional()));
    }

    public function validarPerfil(ValidarTiendanubePrecioCsvPerfilRequest $request): JsonResponse
    {
        try {
            $perfil = $this->perfiles->validarPlantilla(
                $request->file('plantilla'),
                $this->storeId(),
                (int) $request->user()->id
            );
        } catch (TiendanubePrecioCsvException $e) {
            return $this->jsonError($e);
        }

        return response()->json($this->perfiles->serializar($perfil));
    }

    public function generarLote(GenerarTiendanubePrecioCsvRequest $request, string $id): JsonResponse
    {
        try {
            $payload = $this->exportar->generarDesdeLote(
                $id,
                $this->storeId(),
                (int) $request->user()->id,
                (string) ($request->validated('preset') ?: TiendanubePrecioCsvColumnaCatalogo::PRESET_SOLO_PRECIOS),
                $request->validated('columnas')
            );
        } catch (TiendanubePrecioCsvException|TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload, 201);
    }

    public function listarLote(Request $request, string $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.exportar');

        try {
            $payload = $this->exportar->listarPorLote($id, $this->storeId(), (int) $request->user()->id);
        } catch (TiendanubePrecioCsvException|TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json(['data' => $payload]);
    }

    public function generarCatalogo(GenerarTiendanubePrecioCsvRequest $request): JsonResponse
    {
        try {
            $ids = $request->validated('variante_ids') ?? [];
            if ($ids === [] && $request->validated('selection_id')) {
                $ids = $this->idsDeSeleccion(
                    (string) $request->validated('selection_id'),
                    $this->storeId(),
                    (int) $request->user()->id
                );
            }
            $payload = $this->exportar->generarDesdeCatalogo(
                $ids,
                $this->storeId(),
                (int) $request->user()->id,
                (string) ($request->validated('preset') ?: TiendanubePrecioCsvColumnaCatalogo::PRESET_PRODUCTO_COMPLETO),
                $request->validated('columnas')
            );
        } catch (TiendanubePrecioCsvException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload, 201);
    }

    public function descargar(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $this->authorize('tiendanube.precios.exportar');

        try {
            $datos = $this->exportar->descargar($id, $this->storeId(), (int) $request->user()->id);
        } catch (TiendanubePrecioCsvException|TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->streamDownload(function () use ($datos): void {
            $in = fopen($datos['path'], 'r');
            if ($in === false) {
                return;
            }
            fpassthru($in);
            fclose($in);
        }, $datos['nombre'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function declararImportacion(Request $request, int $id): JsonResponse
    {
        $this->authorize('tiendanube.precios.exportar');

        try {
            $payload = $this->exportar->declararImportacion($id, $this->storeId(), (int) $request->user()->id);
        } catch (TiendanubePrecioCsvException|TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->json($payload);
    }

    public function reporteRevision(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $this->authorize('tiendanube.precios.exportar');

        try {
            $datos = $this->reporte->generar($id, $this->storeId(), (int) $request->user()->id);
        } catch (TiendanubePrecioCsvException|TiendanubePrecioLoteException $e) {
            return $this->jsonError($e);
        }

        return response()->streamDownload(function () use ($datos): void {
            $in = fopen($datos['path'], 'r');
            if ($in === false) {
                return;
            }
            fpassthru($in);
            fclose($in);
        }, $datos['nombre'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<int>
     */
    private function idsDeSeleccion(string $selectionId, int $storeId, int $userId): array
    {
        $seleccion = TiendanubePrecioSeleccion::query()
            ->where('id', $selectionId)
            ->where('store_id', $storeId)
            ->where('user_id', $userId)
            ->first();
        if (! $seleccion) {
            throw new TiendanubePrecioCsvException('Selección no encontrada.', 'no_encontrada', 404);
        }

        return $seleccion->miembros()->orderBy('variante_id')->pluck('variante_id')->map(fn ($v) => (int) $v)->all();
    }

    private function jsonError(TiendanubePrecioCsvException|TiendanubePrecioLoteException $e): JsonResponse
    {
        $codigo = $e instanceof TiendanubePrecioCsvException ? $e->codigo : $e->codigo;
        $errores = $e instanceof TiendanubePrecioCsvException ? $e->errores : $e->errores;

        return response()->json([
            'message' => $e->getMessage(),
            'codigo' => $codigo,
            'errores' => $errores,
        ], $e instanceof TiendanubePrecioCsvException ? $e->httpStatus : $e->httpStatus);
    }

    private function storeId(): int
    {
        $id = TiendanubeConfiguracion::obtener()->store_id;
        if (! $id) {
            throw new TiendanubePrecioCsvException('No hay tienda configurada.', 'tienda_faltante', 422);
        }

        return (int) $id;
    }

    private function storeIdOpcional(): int
    {
        return (int) (TiendanubeConfiguracion::obtener()->store_id ?: 0);
    }
}
