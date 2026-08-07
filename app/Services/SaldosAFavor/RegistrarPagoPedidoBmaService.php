<?php

namespace App\Services\SaldosAFavor;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegistrarPagoPedidoBmaService
{
    public function handle(PedidoBma $pedido, array $datos, ?UploadedFile $comprobante = null, ?int $usuarioId = null): PedidoBmaPago
    {
        $monto = round((float) ($datos['monto'] ?? 0), 2);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto de la exhibición debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($pedido, $datos, $comprobante, $usuarioId, $monto) {
            $siguiente = ((int) PedidoBmaPago::where('pedido_bma_id', $pedido->id)->max('numero_exhibicion')) + 1;

            $pago = PedidoBmaPago::create([
                'pedido_bma_id' => $pedido->id,
                'numero_exhibicion' => $siguiente,
                'monto' => $monto,
                'catalogo_banco_id' => $datos['catalogo_banco_id'] ?? null,
                'forma_pago' => $datos['forma_pago'] ?? null,
                'fecha_pago' => $datos['fecha_pago'] ?? now(),
                'referencia' => $datos['referencia'] ?? null,
                'capturado_por_id' => $usuarioId,
                'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            if ($comprobante) {
                $ruta = $comprobante->store("pedidos_bma/pagos/{$pedido->id}", 'public');
                $pago->update([
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $comprobante->getClientOriginalName(),
                    'mime_type' => $comprobante->getMimeType(),
                    'tamano_bytes' => $comprobante->getSize(),
                ]);
            }

            return $pago->fresh(['banco']);
        });
    }

    public function resumenPago(PedidoBma $pedido, float $saldosAplicados = 0): array
    {
        $recibido = (float) PedidoBmaPago::where('pedido_bma_id', $pedido->id)->sum('monto');
        $totalFinal = (float) ($pedido->total_a_cobrar ?? 0) + (float) ($pedido->saldo_a_favor ?? 0);
        // total_a_cobrar ya descuenta saldo; reconstruimos subtotal cobrable.
        $subtotal = round($totalFinal, 2);
        $saldos = $saldosAplicados > 0 ? $saldosAplicados : (float) ($pedido->saldo_a_favor ?? 0);
        $pendiente = round($subtotal - $saldos - $recibido, 2);
        $excedente = round(max($recibido + $saldos - $subtotal, 0), 2);

        $estado = 'sin_pago';
        if ($recibido <= 0 && $saldos <= 0) {
            $estado = 'sin_pago';
        } elseif ($pendiente > 0.01) {
            $estado = 'parcialmente_pagado';
        } elseif ($excedente > 0.01) {
            $estado = 'sobrepagado';
        } else {
            $pendientesRevision = PedidoBmaPago::where('pedido_bma_id', $pedido->id)
                ->where('estado_revision', PedidoBmaPago::REVISION_PENDIENTE)
                ->exists();
            $estado = $pendientesRevision ? 'cubierto_pendiente_revision' : 'pagado_revisado';
        }

        return [
            'total_final' => $subtotal,
            'saldos_aplicados' => $saldos,
            'total_recibido' => round($recibido, 2),
            'pendiente' => max($pendiente, 0),
            'excedente' => $excedente,
            'estado_pago' => $estado,
            'nuevo_saldo_sugerido' => $excedente,
        ];
    }
}
