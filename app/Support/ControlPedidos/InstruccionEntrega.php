<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La instrucción vigente es la única tarea de preparación abierta del pedido.
 * Una tarea sustituida, cancelada o liberada queda como historial.
 */
final class InstruccionEntrega
{
    public const MENSAJE_OBSOLETA = 'Esta instrucción ya no está vigente. Actualice la página para continuar con la instrucción actual.';

    public const MENSAJE_AMBIGUA = 'Hay más de una instrucción abierta para este pedido. La acción no se ejecuta hasta corregir esa inconsistencia.';

    public const MENSAJE_PAQUETERIA = 'La mercancía ya se entregó al transporte. El cambio de paquetería no se admite y no queda registrado como realizado.';

    public const MENSAJE_SEGUNDO_TRASLADO = 'La mercancía ya salió. Un segundo traslado requiere autorización de gerencia.';

    public const MENSAJE_RECOLECCION_TERMINAL = 'La recogida hacia terminal ya ocurrió. No se convierte esta entrega a recolección en sucursal.';

    /**
     * @return Collection<int, PedidoBmaTareaPreparacion>
     */
    public static function abiertas(int $pedidoId): Collection
    {
        return PedidoBmaTareaPreparacion::query()
            ->where('pedido_bma_id', $pedidoId)
            ->whereNotIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
                PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA,
                PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
            ])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Misma coordinación que la sustitución: pedido, luego la instrucción, dentro de la transacción.
     * Una acción que llegó con la instrucción anterior no continúa sobre la nueva.
     */
    public static function bloquearParaEjecutar(PedidoBmaTareaPreparacion $tarea): PedidoBmaTareaPreparacion
    {
        if (DB::transactionLevel() < 1) {
            throw new \RuntimeException('La instrucción vigente se comprueba dentro de la transacción.');
        }

        $pedido = PedidoBma::query()->whereKey((int) $tarea->pedido_bma_id)->lockForUpdate()->first();
        if ($pedido === null) {
            throw ValidationException::withMessages([
                'instruccion' => self::MENSAJE_OBSOLETA,
            ]);
        }

        $vigente = PedidoBmaTareaPreparacion::query()->whereKey($tarea->id)->lockForUpdate()->first();
        if ($vigente === null) {
            throw ValidationException::withMessages([
                'instruccion' => self::MENSAJE_OBSOLETA,
            ]);
        }

        self::assertEjecutable($vigente);

        return $vigente;
    }

    public static function assertEjecutable(PedidoBmaTareaPreparacion $tarea): void
    {
        if (in_array($tarea->estado, [
            PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
            PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA,
            PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
        ], true)) {
            throw ValidationException::withMessages([
                'instruccion' => self::MENSAJE_OBSOLETA,
            ]);
        }

        $abiertas = self::abiertas((int) $tarea->pedido_bma_id);
        if ($abiertas->count() > 1) {
            throw ValidationException::withMessages([
                'instruccion' => self::MENSAJE_AMBIGUA,
            ]);
        }

        $vigente = $abiertas->first();
        if ($vigente === null || (int) $vigente->id !== (int) $tarea->id) {
            throw ValidationException::withMessages([
                'instruccion' => self::MENSAJE_OBSOLETA,
            ]);
        }
    }

    public static function assertCambioPaqueteria(PedidoBma $pedido, ?int $paqueteriaId): void
    {
        if ($paqueteriaId === null || $paqueteriaId === (int) $pedido->catalogo_paqueteria_id) {
            return;
        }

        $pedido->loadMissing('estatus');
        if (! self::entregadaAlTransporte($pedido)) {
            return;
        }

        throw new \RuntimeException(self::MENSAJE_PAQUETERIA);
    }

    public static function assertGuiaNoPosterior(PedidoBma $pedido): void
    {
        $pedido->loadMissing('estatus');
        if (self::entregadaAlTransporte($pedido)) {
            throw new \RuntimeException(self::MENSAJE_PAQUETERIA);
        }
    }

    public static function assertSegundoTraslado(User $usuario, PedidoBma $pedido): void
    {
        if (! self::mercanciaYaSalio($pedido)) {
            return;
        }

        $esQuienVendio = (int) $usuario->id === (int) $pedido->vendedor_id;
        if ($esQuienVendio || ! $usuario->can('control_pedidos.salida.autorizar')) {
            throw new \RuntimeException(self::MENSAJE_SEGUNDO_TRASLADO);
        }
    }

    public static function mercanciaYaSalio(PedidoBma $pedido): bool
    {
        if (in_array($pedido->estatus_sucursal, [
            EstatusSucursalPedidoBma::EN_TRANSITO,
            EstatusSucursalPedidoBma::RECIBIDO,
            EstatusSucursalPedidoBma::EN_RECEPCION,
            EstatusSucursalPedidoBma::EN_CUSTODIA,
            EstatusSucursalPedidoBma::CUSTODIA_PARCIAL,
            EstatusSucursalPedidoBma::RECEPCION_GERENTE_PARCIAL,
        ], true)) {
            return true;
        }

        if ($pedido->tareasPreparacion()->where(function ($query) {
            $query->whereNotNull('enviada_cedis_at')
                ->orWhereIn('estado', [
                    PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
                    PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
                ]);
        })->exists()) {
            return true;
        }

        return PedidoBmaCumplimientoFisico::query()
            ->whereHas('tarea', fn ($query) => $query->where('pedido_bma_id', $pedido->id))
            ->whereIn('estado', [
                PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA,
                PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA,
            ])
            ->exists();
    }

    public static function entregadaAlTransporte(PedidoBma $pedido): bool
    {
        return in_array($pedido->estatus?->fase_ciclo, [
            CatalogoEstatusPedido::FASE_ENVIADO,
            CatalogoEstatusPedido::FASE_EN_RUTA,
            CatalogoEstatusPedido::FASE_ENTREGADO,
        ], true);
    }
}
