<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InformarEntregaResguardoPdvService
{
    public function __construct(
        private readonly RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(ResguardoPdv $resguardo, ResguardoPdvEntrega $entrega, int $actorId): bool
    {
        if ($this->integracionCompletada($entrega)) {
            return true;
        }

        $pedidoId = (int) ($resguardo->pedido_bma_id ?? $entrega->pedido_bma_id);
        if ($pedidoId < 1) {
            $this->registrarFallo($entrega, 'El resguardo no está vinculado a un pedido BMA.');

            return false;
        }

        try {
            DB::transaction(function () use ($pedidoId, $entrega, $actorId, $resguardo) {
                $pedido = PedidoBma::query()
                    ->with('estatus')
                    ->lockForUpdate()
                    ->findOrFail($pedidoId);

                $faseActual = $pedido->estatus?->fase_ciclo;
                $entregado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_ENTREGADO);

                if (! $entregado) {
                    throw new \RuntimeException('No se encontró el estatus ENTREGADO.');
                }

                if ($faseActual === CatalogoEstatusPedido::FASE_ENTREGADO) {
                    if ($this->historialEntregaExiste($pedidoId, (int) $entrega->id)) {
                        $this->marcarIntegracionCompletada($entrega);

                        return;
                    }

                    throw new \RuntimeException('El pedido ya está entregado por otra vía.');
                }

                MaquinaEstadosPedidoBma::assertTransicion(
                    $faseActual,
                    CatalogoEstatusPedido::FASE_ENTREGADO
                );

                $estatusAnterior = $pedido->estatus;

                $pedido->update([
                    'catalogo_estatus_pedido_id' => $entregado->id,
                ]);

                $this->historialService->registrarTransicion(
                    $pedido->id,
                    $actorId,
                    $estatusAnterior,
                    $entregado,
                    $this->comentarioHistorial($resguardo, $entrega),
                    AccionesHistorialPedidoBma::ENTREGA_PDV
                );

                $this->marcarIntegracionCompletada($entrega);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('[PDV] Fallo al informar entrega a Control de pedidos', [
                'resguardo_id' => $resguardo->id,
                'entrega_id' => $entrega->id,
                'pedido_bma_id' => $pedidoId,
                'error' => $e->getMessage(),
            ]);
            $this->registrarFallo($entrega, $e->getMessage());

            return false;
        }
    }

    private function integracionCompletada(ResguardoPdvEntrega $entrega): bool
    {
        $snapshot = $entrega->snapshot_json ?? [];

        return ($snapshot['integracion_cp']['estado'] ?? null) === 'completada';
    }

    private function historialEntregaExiste(int $pedidoId, int $entregaId): bool
    {
        return PedidoBmaHistorialEstado::query()
            ->where('pedido_bma_id', $pedidoId)
            ->where('accion', AccionesHistorialPedidoBma::ENTREGA_PDV)
            ->where('comentarios', 'like', '%entrega_pdv_id:'.$entregaId.'%')
            ->exists();
    }

    private function comentarioHistorial(ResguardoPdv $resguardo, ResguardoPdvEntrega $entrega): string
    {
        $relacion = $entrega->relacion === ResguardoPdvEntrega::RELACION_TERCERO ? 'tercero' : 'titular';

        return sprintf(
            'Entrega presencial en sucursal. entrega_pdv_id:%d resguardo_id:%d receptor:%s relacion:%s',
            $entrega->id,
            $resguardo->id,
            $entrega->nombre_quien_retira,
            $relacion
        );
    }

    private function marcarIntegracionCompletada(ResguardoPdvEntrega $entrega): void
    {
        $snapshot = $entrega->snapshot_json ?? [];
        $integracion = $snapshot['integracion_cp'] ?? [];
        $integracion['estado'] = 'completada';
        $integracion['idempotency_key'] = $entrega->idempotency_key;
        $integracion['completada_at'] = now()->toIso8601String();
        $integracion['ultimo_error'] = null;
        $snapshot['integracion_cp'] = $integracion;

        $entrega->update(['snapshot_json' => $snapshot]);
    }

    private function registrarFallo(ResguardoPdvEntrega $entrega, string $mensaje): void
    {
        $snapshot = $entrega->snapshot_json ?? [];
        $integracion = $snapshot['integracion_cp'] ?? [];
        $integracion['estado'] = 'pendiente';
        $integracion['idempotency_key'] = $entrega->idempotency_key;
        $integracion['intentos'] = ((int) ($integracion['intentos'] ?? 0)) + 1;
        $integracion['ultimo_error'] = $mensaje;
        $integracion['ultimo_intento_at'] = now()->toIso8601String();
        $snapshot['integracion_cp'] = $integracion;

        $entrega->update(['snapshot_json' => $snapshot]);
    }
}
