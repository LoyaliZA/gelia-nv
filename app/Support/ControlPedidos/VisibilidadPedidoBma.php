<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reglas de aislamiento Control Pedidos:
 * - Vendedora: solo sus pedidos (mutar/consultar propios).
 * - Gerente: consulta pedidos de colaboradores y de su departamento, excepto borradores ajenos; muta solo los propios.
 * - Super Admin: consulta todos (omnisciencia); muta solo los propios como vendedora.
 * - Auxiliar: bandeja de auditoría del departamento (permiso + fase).
 * - CEDIS/Delegado: bandeja por permiso + fase (sin filtro dept).
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
        $ids = array_merge($ids, $colaboradores);

        if ($usuario->hasRole('Gerente') || $usuario->can('control_pedidos.auditar')) {
            $deptos = self::idsDepartamentos($usuario);
            if ($deptos !== []) {
                $deptoUsers = User::query()
                    ->where(function ($q) use ($deptos) {
                        $q->whereIn('departamento_id', $deptos)
                            ->orWhereHas('departamentos', fn ($d) => $d->whereIn('departamentos.id', $deptos));
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $ids = array_merge($ids, $deptoUsers);
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> */
    public static function idsDepartamentos(User $usuario): array
    {
        $ids = [];
        if ($usuario->departamento_id) {
            $ids[] = (int) $usuario->departamento_id;
        }
        $usuario->loadMissing('departamentos');
        foreach ($usuario->departamentos as $depto) {
            $ids[] = (int) $depto->id;
        }

        return array_values(array_unique($ids));
    }

    public static function aplicarAlcanceListadoBma(Builder $query, User $usuario): void
    {
        $ids = self::idsVendedoresVisibles($usuario);
        if ($ids === null) {
            return;
        }

        $query->whereIn('vendedor_id', $ids);
        self::excluirBorradoresAjenos($query, $usuario);
    }

    public static function puedeMutarComoVendedora(User $usuario, PedidoBma $pedido): bool
    {
        return (int) $pedido->vendedor_id === (int) $usuario->id;
    }

    /** Consulta en panel BMA: propios + equipo del gerente (sin borradores ajenos). */
    public static function puedeConsultarEnListadoBma(User $usuario, PedidoBma $pedido): bool
    {
        if (self::esAdmin($usuario)) {
            return true;
        }

        if (self::esBorradorAjeno($usuario, $pedido)) {
            return false;
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

        if ($usuario->can('control_pedidos.auditar') && in_array($fase, self::FASES_AUDITORIA, true)
            && self::vendedorEnAlcance($usuario, $pedido)) {
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

    public static function assertPuedeConsultar(User $usuario, PedidoBma $pedido): void
    {
        if (! self::puedeConsultar($usuario, $pedido)) {
            abort(403, 'No tienes autorización para consultar este pedido.');
        }
    }

    public static function assertPuedeMutarComoVendedora(User $usuario, PedidoBma $pedido): void
    {
        if (! self::puedeMutarComoVendedora($usuario, $pedido)) {
            abort(403, 'No tienes autorización para modificar este pedido.');
        }
    }

    private static function vendedorEnAlcance(User $usuario, PedidoBma $pedido): bool
    {
        $ids = self::idsVendedoresVisibles($usuario);
        if ($ids === null) {
            return true;
        }

        return in_array((int) $pedido->vendedor_id, $ids, true);
    }

    private static function esBorradorAjeno(User $usuario, PedidoBma $pedido): bool
    {
        if ((int) $pedido->vendedor_id === (int) $usuario->id) {
            return false;
        }

        $pedido->loadMissing('estatus');

        return $pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_BORRADOR;
    }

    private static function excluirBorradoresAjenos(Builder $query, User $usuario): void
    {
        $idsBorrador = CatalogoEstatusPedido::query()
            ->where('fase_ciclo', CatalogoEstatusPedido::FASE_BORRADOR)
            ->pluck('id')
            ->all();

        if ($idsBorrador === []) {
            return;
        }

        $query->where(function (Builder $q) use ($usuario, $idsBorrador) {
            $q->where('vendedor_id', (int) $usuario->id)
                ->orWhereNotIn('catalogo_estatus_pedido_id', $idsBorrador);
        });
    }

    private static function enBandejaCedis(PedidoBma $pedido, ?string $fase): bool
    {
        if ($pedido->estatus_envio === PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE) {
            return true;
        }

        return in_array($fase, self::FASES_CEDIS, true);
    }
}
