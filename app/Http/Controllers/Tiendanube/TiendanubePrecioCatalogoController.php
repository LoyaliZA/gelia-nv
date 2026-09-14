<?php

namespace App\Http\Controllers\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubePrecioSeleccionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tiendanube\ListarTiendanubePrecioCatalogoRequest;
use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioLista;
use App\Services\Tiendanube\Precios\TiendanubePrecioCatalogoFiltros;
use App\Services\Tiendanube\Precios\TiendanubePrecioCatalogoQueryService;
use App\Services\Tiendanube\Precios\TiendanubePrecioReglaMetadatosService;
use App\Services\Tiendanube\Precios\TiendanubePrecioSeleccionService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TiendanubePrecioCatalogoController extends Controller
{
    public function __construct(
        private readonly TiendanubePrecioCatalogoQueryService $catalogo,
        private readonly TiendanubePrecioSeleccionService $selecciones,
        private readonly TiendanubePrecioReglaMetadatosService $reglaMetadatos,
    ) {}

    public function index(ListarTiendanubePrecioCatalogoRequest $request): Response
    {
        $user = $request->user();
        $config = TiendanubeConfiguracion::obtener();
        $storeId = $config->store_id ? (int) $config->store_id : 0;
        $puedeVerCosto = $user->can('tiendanube.precios.ver');
        $filtros = TiendanubePrecioCatalogoFiltros::fromArray($request->validated());
        $listado = $this->catalogo->listar($filtros, $storeId, $puedeVerCosto);
        $seleccion = $this->seleccionSiHay($request, $storeId, (int) $user->id, $filtros, $listado['data'], $puedeVerCosto);

        $listasCount = $storeId > 0
            ? TiendanubePrecioLista::query()->where('store_id', $storeId)->activas()->count()
            : 0;

        return Inertia::render('Tiendanube/Precios/Index', [
            'configuracion' => [
                'store_id' => $storeId ?: null,
                'store_name' => $config->store_name,
                'credenciales_configuradas' => $config->credencialesConfiguradas(),
            ],
            'catalogo' => $listado,
            'categorias' => $this->catalogo->categoriasFiltro(),
            'sync' => $this->catalogo->contextoSync(),
            'seleccion' => $seleccion,
            'hay_listas' => $listasCount > 0,
            'metadatos_reglas' => $storeId > 0 && $user->can('tiendanube.precios.reglas.ver')
                ? $this->reglaMetadatos->paraUi($storeId)
                : null,
            'permisos' => [
                'ver' => $user->can('tiendanube.ver'),
                'sincronizar' => $user->can('tiendanube.sincronizar'),
                'precios_ver' => $puedeVerCosto,
                'precios_editar' => $user->can('tiendanube.precios.editar'),
                'reglas_ver' => $user->can('tiendanube.precios.reglas.ver'),
                'reglas_administrar' => $user->can('tiendanube.precios.reglas.administrar'),
                'precios_aprobar' => $user->can('tiendanube.precios.aprobar'),
                'precios_exportar' => $user->can('tiendanube.precios.exportar'),
                'precios_aplicar' => $user->can('tiendanube.precios.aplicar'),
                'configurar' => $user->can('tiendanube.configurar'),
                'restaurar' => $user->can('tiendanube.precios.reglas.ver')
                    && (bool) config('tiendanube.precios_restauracion_habilitada', true),
            ],
        ]);
    }

    public function listar(ListarTiendanubePrecioCatalogoRequest $request): JsonResponse
    {
        $user = $request->user();
        $config = TiendanubeConfiguracion::obtener();
        $storeId = $config->store_id ? (int) $config->store_id : 0;
        $puedeVerCosto = $user->can('tiendanube.precios.ver');
        $filtros = TiendanubePrecioCatalogoFiltros::fromArray($request->validated());
        $listado = $this->catalogo->listar($filtros, $storeId, $puedeVerCosto);
        $listado['seleccion'] = $this->seleccionSiHay($request, $storeId, (int) $user->id, $filtros, $listado['data'], $puedeVerCosto);
        $listado['sync'] = $this->catalogo->contextoSync();

        return response()->json($listado);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>|null
     */
    private function seleccionSiHay(
        ListarTiendanubePrecioCatalogoRequest $request,
        int $storeId,
        int $userId,
        TiendanubePrecioCatalogoFiltros $filtros,
        array $filas,
        bool $puedeVerCosto,
    ): ?array {
        $selectionId = $request->validated('selection_id');
        if (! $selectionId || $storeId <= 0) {
            return null;
        }

        $idsPagina = array_map(fn (array $fila) => (int) $fila['variante_id'], $filas);

        try {
            return $this->selecciones->adjuntarAListado(
                $selectionId,
                $storeId,
                $userId,
                $filtros,
                $idsPagina,
                $puedeVerCosto
            );
        } catch (TiendanubePrecioSeleccionException $e) {
            if (in_array($e->httpStatus, [403, 404, 410], true)) {
                return null;
            }

            throw $e;
        }
    }
}
