<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCancelacionOperativa;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarcarEsperaPagoPedidoService
{
    public function __construct(
        private CancelacionOperativaConfig $config,
        private CalcularFechaLimiteResguardoService $fechaLimiteService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(PedidoBma $pedido, User $usuario): PedidoBma
    {
        if (! $usuario->can('control_pedidos.espera_pago')) {
            throw new \RuntimeException('No tiene permiso para marcar espera de pago.');
        }

        if (! $this->config->activo() || ! $this->config->usuarioHabilitado($usuario)) {
            throw ValidationException::withMessages([
                'flag' => 'La espera de pago / cancelación operativa no está habilitada para su usuario.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);

            if ($pedido->cancelado_at || $pedido->tieneCancelacionOperativaActiva()) {
                throw ValidationException::withMessages([
                    'estado' => 'El pedido está cancelado o tiene una cancelación operativa activa.',
                ]);
            }

            if ($pedido->esperando_pago_at) {
                return $pedido->fresh(['tareasPreparacion', 'estatus']);
            }

            $tareas = PedidoBmaTareaPreparacion::query()
                ->where('pedido_bma_id', $pedido->id)
                ->whereIn('estado', [
                    PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                    PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
                ])
                ->lockForUpdate()
                ->get();

            if ($tareas->isEmpty()) {
                throw ValidationException::withMessages([
                    'tareas' => 'Solo puede marcar espera de pago cuando hay mercancía separada (tareas respondidas).',
                ]);
            }

            $calc = $this->fechaLimiteService->calcular();
            $ahora = now($this->config->zonaHoraria());

            foreach ($tareas as $tarea) {
                $tarea->update([
                    'espera_pago_at' => $ahora,
                    'fecha_limite' => $calc['fecha_limite'],
                    'regla_plazo_snapshot' => $calc['snapshot'],
                    'version' => $tarea->version + 1,
                ]);
            }

            $pedido->update([
                'esperando_pago_at' => $ahora,
                'es_resguardo' => true,
            ]);

            $limiteTxt = $calc['fecha_limite']->timezone($this->config->zonaHoraria())->format('d/m/Y H:i');
            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                "Pedido en espera de pago. Fecha límite: {$limiteTxt}.",
                AccionesHistorialPedidoBma::ESPERA_PAGO
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(),
                'pedido_espera_pago',
                "Pedido en espera de pago. Límite: {$limiteTxt}.",
                ['control_pedidos.tienda.ver', 'control_pedidos.cedis'],
                $usuario->id,
                true,
                ['url' => '/control-pedidos']
            );

            return $pedido->fresh(['tareasPreparacion', 'estatus', 'cliente']);
        });
    }

    public function salirPorPago(PedidoBma $pedido, User $usuario): PedidoBma
    {
        if (! $pedido->esperando_pago_at) {
            return $pedido;
        }

        return DB::transaction(function () use ($pedido, $usuario) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if (! $pedido->esperando_pago_at) {
                return $pedido;
            }

            $pedido->update(['esperando_pago_at' => null]);

            PedidoBmaTareaPreparacion::query()
                ->where('pedido_bma_id', $pedido->id)
                ->whereNotNull('espera_pago_at')
                ->update(['espera_pago_at' => null]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                'Salida de espera de pago (pago registrado/validado).',
                AccionesHistorialPedidoBma::SALIDA_ESPERA_PAGO
            );

            return $pedido->fresh();
        });
    }
}
