<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\PuntoVenta\ResguardoPdv;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Una remisión pagada no puede pertenecer a dos envíos. Opción A: un criterio, dos mecanismos físicos.
 */
final class ExclusividadRemisionEnvio
{
    private const FASES_CON_ENVIO_PROPIO = [
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        CatalogoEstatusPedido::FASE_EN_RUTA,
        CatalogoEstatusPedido::FASE_ENVIADO,
        CatalogoEstatusPedido::FASE_ENTREGADO,
        CatalogoEstatusPedido::FASE_CANCELADO,
    ];

    public static function assertDisponible(PedidoBma $origen, PedidoBma $envio): void
    {
        if ((int) $origen->id === (int) $envio->id) {
            throw new InvalidArgumentException('Una remisión no se anexa a sí misma.');
        }
        if ((int) $origen->cliente_id !== (int) $envio->cliente_id) {
            throw new InvalidArgumentException('La remisión y el envío deben ser del mismo cliente.');
        }
        if ($origen->cancelado_at || $envio->cancelado_at) {
            throw new InvalidArgumentException('No se vincula una remisión o un envío cancelado.');
        }

        $principal = $origen->pedido_principal_id;
        if ($principal && (int) $principal !== (int) $envio->id) {
            throw new InvalidArgumentException('Esa remisión ya forma parte de otro envío.');
        }

        $anexo = $origen->envioQueLoAnexa;
        if ($anexo && (int) $anexo->pedido_envio_id !== (int) $envio->id) {
            throw new InvalidArgumentException('Esa remisión ya está anexada a otro envío.');
        }

        if ($origen->pedido_principal_id && (int) $origen->pedido_principal_id === (int) $origen->id) {
            throw new InvalidArgumentException('Una remisión no se anexa a sí misma.');
        }
        if ($envio->pedido_principal_id && (int) $envio->pedido_principal_id === (int) $origen->id) {
            throw new InvalidArgumentException('No se forma un ciclo entre remisión y envío.');
        }

        $origen->loadMissing('estatus');
        if (in_array($origen->estatus?->fase_ciclo, self::FASES_CON_ENVIO_PROPIO, true)) {
            throw new InvalidArgumentException('Esa remisión ya tiene un envío propio.');
        }

        $entregada = ResguardoPdv::query()
            ->where('pedido_bma_id', $origen->id)
            ->where('estado', ResguardoPdv::ESTADO_ENTREGADO)
            ->exists();
        if ($entregada) {
            throw new InvalidArgumentException('Esa remisión ya fue entregada.');
        }
    }

    /**
     * Orden único de bloqueos para vinculación, integración y liberación: el envío principal primero y después
     * los miembros por id ascendente. Recarga los modelos ya bloqueados (sin relaciones en caché).
     *
     * @template T
     *
     * @param  callable(\Illuminate\Support\Collection<int, PedidoBma>): T  $then
     * @return T
     */
    public static function conLockEnvio(PedidoBma $envio, PedidoBma $origen, callable $then)
    {
        return DB::transaction(function () use ($envio, $origen, $then) {
            $principal = PedidoBma::query()->lockForUpdate()->findOrFail($envio->id);
            $miembros = PedidoBma::query()->whereIn('id', [$origen->id])->orderBy('id')->lockForUpdate()->get();

            return $then($miembros->push($principal)->keyBy('id'));
        });
    }

    /**
     * @template T
     *
     * @param  callable(\Illuminate\Support\Collection<int, PedidoBma>): T  $then
     * @return T
     */
    public static function conLock(PedidoBma $a, PedidoBma $b, callable $then)
    {
        $ids = [$a->id, $b->id];
        sort($ids);

        return DB::transaction(function () use ($ids, $then) {
            $bloqueados = PedidoBma::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            return $then($bloqueados);
        });
    }
}
