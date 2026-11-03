<?php

namespace App\Services\Almacenes;

use App\Models\Inventario;
use App\Models\User;
use App\Support\Almacenes\OrdenamientoListadoAlmacen;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListarInventariosService
{
    public function __construct(
        private readonly AlcanceAlmacenesService $alcance,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function ejecutar(User $usuario, array $filtros = [], int $porPagina = 50): LengthAwarePaginator
    {
        $query = Inventario::query()
            ->select('inventarios.*')
            ->leftJoin('producto_costos', function ($join) {
                $join->on('producto_costos.producto_id', '=', 'inventarios.producto_id')
                    ->on('producto_costos.almacen_id', '=', 'inventarios.almacen_id');
            })
            ->addSelect([
                'producto_costos.costo as costo_valor',
                'producto_costos.precio_venta as precio_venta_valor',
            ])
            ->with([
                'producto.marca',
                'producto.categoria',
                'almacen.sucursal',
                'almacen.tipoAlmacen',
            ]);

        $this->alcance->restringirPorAlmacenSucursalOperable($query, $usuario);

        $sucursalId = ! empty($filtros['sucursal_id']) ? (int) $filtros['sucursal_id'] : null;
        $almacenId = ! empty($filtros['almacen_id']) ? (int) $filtros['almacen_id'] : null;
        $this->alcance->validarContextoConsulta($usuario, $sucursalId, $almacenId);

        if ($sucursalId) {
            $query->whereHas('almacen', fn ($q) => $q->where('sucursal_id', $sucursalId));
        }

        if ($almacenId) {
            $query->where('almacen_id', $almacenId);
        }

        if (! empty($filtros['q'])) {
            $busqueda = (string) $filtros['q'];
            $query->whereHas('producto', fn ($q) => $q->buscarPorTexto($busqueda));
        }

        OrdenamientoListadoAlmacen::inventarios(
            $query,
            isset($filtros['sort']) ? (string) $filtros['sort'] : null,
            isset($filtros['dir']) ? (string) $filtros['dir'] : null,
        );

        return $query->paginate(max(1, $porPagina))->withQueryString();
    }
}
