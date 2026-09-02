<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InformarDevolucionResguardoPdvService
{
    public function __construct(
        private readonly RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(ResguardoPdv $resguardo, ResguardoPdvEvento $evento, int $actorId): bool
    {
        if ($this->integracionCompletada($evento)) {
            return true;
        }

        $pedidoId = (int) $resguardo->pedido_bma_id;
        if ($pedidoId < 1) {
            $this->registrarFallo($evento, 'El resguardo no está vinculado a un pedido BMA.');

            return false;
        }

        try {
            DB::transaction(function () use ($pedidoId, $evento, $actorId, $resguardo) {
                $pedido = PedidoBma::query()
                    ->with('estatus')
                    ->lockForUpdate()
                    ->findOrFail($pedidoId);

                $estatusActual = $pedido->estatus;
                if (! $estatusActual) {
                    throw new \RuntimeException('El pedido no tiene estatus vigente.');
                }

                if ($this->historialDevolucionExiste($pedidoId, (int) $evento->id)) {
                    $this->marcarIntegracionCompletada($evento);

                    return;
                }

                $this->historialService->ejecutar(
                    $pedidoId,
                    $actorId,
                    $estatusActual->id,
                    $estatusActual->id,
                    $this->comentarioHistorial($resguardo, $evento),
                    AccionesHistorialPedidoBma::DEVOLUCION_PDV
                );

                $this->marcarIntegracionCompletada($evento);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('[PDV] Fallo al informar devolución a Control de pedidos', [
                'resguardo_id' => $resguardo->id,
                'evento_id' => $evento->id,
                'pedido_bma_id' => $pedidoId,
                'error' => $e->getMessage(),
            ]);
            $this->registrarFallo($evento, $e->getMessage());

            return false;
        }
    }

    private function integracionCompletada(ResguardoPdvEvento $evento): bool
    {
        $snapshot = $evento->snapshot_json ?? [];

        return ($snapshot['integracion_cp']['estado'] ?? null) === 'completada';
    }

    private function historialDevolucionExiste(int $pedidoId, int $eventoId): bool
    {
        return PedidoBmaHistorialEstado::query()
            ->where('pedido_bma_id', $pedidoId)
            ->where('accion', AccionesHistorialPedidoBma::DEVOLUCION_PDV)
            ->where('comentarios', 'like', '%evento_pdv_id:'.$eventoId.'%')
            ->exists();
    }

    private function comentarioHistorial(ResguardoPdv $resguardo, ResguardoPdvEvento $evento): string
    {
        $cantidad = (int) ($evento->snapshot_json['cantidad_devuelta'] ?? 0);

        return sprintf(
            'Devolución física confirmada en sucursal. evento_pdv_id:%d resguardo_id:%d bultos_devueltos:%d',
            $evento->id,
            $resguardo->id,
            $cantidad
        );
    }

    private function marcarIntegracionCompletada(ResguardoPdvEvento $evento): void
    {
        $snapshot = $evento->snapshot_json ?? [];
        $integracion = $snapshot['integracion_cp'] ?? [];
        $integracion['estado'] = 'completada';
        $integracion['idempotency_key'] = $evento->idempotency_key;
        $integracion['completada_at'] = now()->toIso8601String();
        $integracion['ultimo_error'] = null;
        $snapshot['integracion_cp'] = $integracion;

        $evento->update(['snapshot_json' => $snapshot]);
    }

    private function registrarFallo(ResguardoPdvEvento $evento, string $mensaje): void
    {
        $snapshot = $evento->snapshot_json ?? [];
        $integracion = $snapshot['integracion_cp'] ?? [];
        $integracion['estado'] = 'pendiente';
        $integracion['idempotency_key'] = $evento->idempotency_key;
        $integracion['intentos'] = ((int) ($integracion['intentos'] ?? 0)) + 1;
        $integracion['ultimo_error'] = $mensaje;
        $integracion['ultimo_intento_at'] = now()->toIso8601String();
        $snapshot['integracion_cp'] = $integracion;

        $evento->update(['snapshot_json' => $snapshot]);
    }
}
