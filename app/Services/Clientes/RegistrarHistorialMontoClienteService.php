<?php

namespace App\Services\Clientes;

use App\Models\Cliente;
use App\Models\HistorialMontoCliente;

class RegistrarHistorialMontoClienteService
{
    public const ORIGEN_CARGA_MASIVA = 'carga_masiva';
    public const ORIGEN_SOLICITUD_APROBACION = 'solicitud_aprobacion';
    public const ORIGEN_SOLICITUD_PAGO = 'solicitud_pago';
    public const ORIGEN_SOLICITUD_REVERSION = 'solicitud_reversion';
    public const ORIGEN_CRON_RECHAZO_PAGO = 'cron_rechazo_pago';

    public function registrar(
        Cliente $cliente,
        float $montoNuevo,
        string $origen,
        ?int $usuarioId = null,
        ?int $importacionClienteId = null,
        ?int $solicitudId = null,
        ?float $montoOperacion = null,
        ?string $notas = null,
    ): HistorialMontoCliente {
        $montoAnterior = (float) $cliente->monto_venta_actual;

        if (abs($montoAnterior - $montoNuevo) < 0.001) {
            return new HistorialMontoCliente([
                'cliente_id' => $cliente->id,
                'monto_anterior' => $montoAnterior,
                'monto_nuevo' => $montoNuevo,
            ]);
        }

        return HistorialMontoCliente::create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuarioId,
            'monto_anterior' => $montoAnterior,
            'monto_nuevo' => $montoNuevo,
            'diferencia_aplicada' => $montoNuevo - $montoAnterior,
            'origen' => $origen,
            'importacion_cliente_id' => $importacionClienteId,
            'solicitud_id' => $solicitudId,
            'monto_operacion' => $montoOperacion,
            'notas' => $notas,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function filaBatch(
        Cliente $cliente,
        float $montoNuevo,
        string $origen,
        ?int $usuarioId = null,
        ?int $importacionClienteId = null,
        ?int $solicitudId = null,
        ?float $montoOperacion = null,
        ?string $notas = null,
    ): array {
        $ahora = now();
        $montoAnterior = (float) $cliente->monto_venta_actual;

        return [
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuarioId,
            'monto_anterior' => $montoAnterior,
            'monto_nuevo' => $montoNuevo,
            'diferencia_aplicada' => $montoNuevo - $montoAnterior,
            'origen' => $origen,
            'importacion_cliente_id' => $importacionClienteId,
            'solicitud_id' => $solicitudId,
            'monto_operacion' => $montoOperacion,
            'notas' => $notas,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
    }
}
