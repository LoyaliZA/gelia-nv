<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Collection;

/**
 * Único lugar donde se decide qué miembros de un Multienvío puede ver un usuario (el grupo nunca concede permiso).
 * Solo en nacional la visibilidad depende exclusivamente de vendedora, estatus, estatus_envio y modalidad
 * (no intervienen traslado, cobertura, sucursal ni instrucción), así que se evalúa una vez por combinación
 * durante la vida de la instancia. Cualquier otra modalidad se evalúa pedido por pedido.
 * Requiere `estatus` y `vendedor.departamentos` precargados para no generar N+1.
 */
final class VisibilidadMiembrosMultienvio
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(private readonly User $usuario) {}

    public function puede(PedidoBma $m): bool
    {
        if (($m->solicitud_preparacion_json['modalidad_entrega'] ?? null) !== 'nacional') {
            return VisibilidadPedidoBma::puedeConsultar($this->usuario, $m);
        }
        $clave = implode('|', [$m->vendedor_id, $m->catalogo_estatus_pedido_id, $m->estatus_envio]);

        return $this->cache[$clave] ??= VisibilidadPedidoBma::puedeConsultar($this->usuario, $m);
    }

    /**
     * @param  Collection<int, PedidoBma>  $miembros
     * @return Collection<int, PedidoBma>
     */
    public function filtrar(Collection $miembros): Collection
    {
        return $miembros->filter(fn (PedidoBma $m) => $this->puede($m))->values();
    }
}
