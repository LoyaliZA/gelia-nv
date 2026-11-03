<?php

namespace App\Http\Controllers\Api\V1\Mobile\Almacenes;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\User;
use App\Services\Almacenes\AlcanceAlmacenesService;
use App\Services\Almacenes\ListarInventariosService;
use App\Services\Mobile\Serializar\OperacionMovilSerializer;
use App\Support\Api\V1\AutorizacionMovil;
use App\Support\Api\V1\RespuestaPaginada;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlmacenMobileController extends Controller
{
    /** @var list<string> */
    private const PERMISOS_ALMACEN = [
        'almacenes.inventarios.ver',
        'almacenes.costos.ver',
        'gestion_interna.productos.ver',
        'almacenes.productos.ver',
        'catalogos.gestionar',
    ];

    /** @var list<string> */
    private const PERMISOS_INVENTARIO = [
        'almacenes.inventarios.ver',
        'catalogos.gestionar',
    ];

    public function index(Request $request, AlcanceAlmacenesService $alcance, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, self::PERMISOS_ALMACEN);

        $query = $this->consulta($usuario, $alcance, $request);
        $paginado = $query->orderBy('nombre')->paginate(RespuestaPaginada::porPagina($request));

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (Almacen $almacen) => $serializar->almacen($almacen))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
            'sucursales' => $alcance->sucursalesOperables($usuario)->map(fn ($sucursal) => [
                'id' => $sucursal->id,
                'codigo' => $sucursal->codigo,
                'nombre' => $sucursal->nombre,
            ])->values(),
        ]);
    }

    public function show(Request $request, Almacen $almacen, AlcanceAlmacenesService $alcance, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, self::PERMISOS_ALMACEN);
        $this->asegurarAlmacen($alcance, $usuario, $almacen);
        $almacen->load(['sucursal', 'tipoAlmacen']);

        return response()->json([
            'data' => $serializar->almacen($almacen),
        ]);
    }

    public function inventarios(Request $request, ListarInventariosService $listar, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, self::PERMISOS_INVENTARIO);

        $paginado = $listar->ejecutar(
            $usuario,
            $request->only(['sucursal_id', 'almacen_id', 'q', 'sort', 'dir']),
            RespuestaPaginada::porPagina($request, 25),
        );

        return response()->json([
            'data' => $paginado->getCollection()->map(fn ($inventario) => $serializar->inventario($inventario))->values(),
            'meta' => RespuestaPaginada::meta($paginado),
        ]);
    }

    public function inventario(Request $request, int $inventario, AlcanceAlmacenesService $alcance, OperacionMovilSerializer $serializar): JsonResponse
    {
        $usuario = $this->usuario($request);
        AutorizacionMovil::alguno($usuario, self::PERMISOS_INVENTARIO);

        $registro = \App\Models\Inventario::query()
            ->with(['producto.marca', 'producto.categoria', 'almacen.sucursal', 'almacen.tipoAlmacen'])
            ->find($inventario);

        if ($registro === null) {
            abort(404, 'Inventario no encontrado.');
        }

        $this->asegurarAlmacen($alcance, $usuario, $registro->almacen);

        return response()->json([
            'data' => $serializar->inventario($registro),
        ]);
    }

    private function consulta(User $usuario, AlcanceAlmacenesService $alcance, Request $request): Builder
    {
        $query = Almacen::query()->with(['sucursal', 'tipoAlmacen']);
        $alcance->restringirQueryAlmacenes($query, $usuario);

        if ($request->filled('q')) {
            $termino = (string) $request->query('q');
            $query->where(function (Builder $inner) use ($termino) {
                $inner->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('codigo', 'like', "%{$termino}%");
            });
        }

        if ($request->query('activo') !== null && $request->query('activo') !== '') {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('sucursal_id')) {
            $alcance->asegurarSucursalOperable($usuario, (int) $request->query('sucursal_id'));
            $query->where('sucursal_id', (int) $request->query('sucursal_id'));
        }

        return $query;
    }

    private function asegurarAlmacen(AlcanceAlmacenesService $alcance, User $usuario, ?Almacen $almacen): void
    {
        if ($almacen === null) {
            abort(404, 'Almacén no encontrado.');
        }

        try {
            $alcance->asegurarAlmacenOperable($usuario, $almacen->id);
        } catch (AuthorizationException) {
            abort(404, 'Almacén no encontrado.');
        }
    }

    private function usuario(Request $request): User
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $usuario;
    }
}
