<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reglas de aislamiento Control Pedidos:
 * - Vendedora: solo sus pedidos (mutar/consultar propios).
 * - Gerente: consulta pedidos de colaboradores; muta solo los propios.
 * - Auxiliar/CEDIS/Delegado: bandeja por permiso + fase (sin filtro dept).
 */
final class VisibilidadPedidoBma
{
    private const ROLES_ADMIN = [
        'Super Admin',
        'Administrador',
        'Admin',
        'Super admin (admin)',
    ];

    private const FASES_AUDITORIA = [
        CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
        CatalogoEstatusPedido::FASE_EN_CEDIS,
        CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
        CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        CatalogoEstatusPedido::FASE_ENTREGADO,
        CatalogoEstatusPedido::FASE_ENVIADO,
    ];

    private const FASES_CEDIS = [
        CatalogoEstatusPedido::FASE_EN_CEDIS,
        CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        CatalogoEstatusPedido::FASE_ENTREGADO,
        CatalogoEstatusPedido::FASE_ENVIADO,
    ];

    private const FASES_DELEGADO = [
        CatalogoEstatusPedido::FASE_EN_CEDIS,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        CatalogoEstatusPedido::FASE_ENVIADO,
    ];

    public static function esAdmin(User $usuario): bool
    {
        return $usuario->hasAnyRole(self::ROLES_ADMIN);
    }

    /**
     * null = sin filtro (admin). Si no, lista de vendedor_id visibles en listado BMA.
     *
     * @return list<int>|null
     */
    public static function idsVendedoresVisibles(User $usuario): ?array
    {
        if (self::esAdmin($usuario)) {
            return null;
        }

        $ids = [(int) $usuario->id];
        $colaboradores = $usuario->colaboradores()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_unique(array_merge($ids, $colaboradores)));
    }

    public static function aplicarAlcanceListadoBma(Builder $query, User $usuario): void
    {
        $ids = self::idsVendedoresVisibles($usuario);
        if ($ids === null) {
            return;
        }

        $query->whereIn('vendedor_id', $ids);
    }

    public static function puedeMutarComoVendedora(User $usuario, PedidoBma $pedido): bool
    {
        if (self::esAdmin($usuario)) {
            return true;
        }

        return (int) $pedido->vendedor_id === (int) $usuario->id;
    }

    /** Consulta en panel BMA: propios + equipo del gerente. */
    public static function puedeConsultarEnListadoBma(User $usuario, PedidoBma $pedido): bool
    {
        if (self::esAdmin($usuario)) {
            return true;
        }

        $ids = self::idsVendedoresVisibles($usuario) ?? [];

        return in_array((int) $pedido->vendedor_id, $ids, true);
    }

    /** Consulta general (detalle, bitácora, documentos): BMA + bandejas por permiso. */
    public static function puedeConsultar(User $usuario, PedidoBma $pedido): bool
    {
        if (self::puedeConsultarEnListadoBma($usuario, $pedido)) {
            return true;
        }

        $pedido->loadMissing('estatus');
        $fase = $pedido->estatus?->fase_ciclo;

        if ($usuario->can('control_pedidos.auditar') && in_array($fase, self::FASES_AUDITORIA, true)) {
            return true;
        }

        if ($usuario->can('control_pedidos.cedis') && self::enBandejaCedis($pedido, $fase)) {
            return true;
        }

        if ($usuario->can('control_pedidos.delegado') && in_array($fase, self::FASES_DELEGADO, true)) {
            return true;
        }

        return false;
    }

    private static function enBandejaCedis(PedidoBma $pedido, ?string $fase): bool
    {
        if ($pedido->estatus_envio === PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE) {
            return true;
        }

        return in_array($fase, self::FASES_CEDIS, true);
    }
}
