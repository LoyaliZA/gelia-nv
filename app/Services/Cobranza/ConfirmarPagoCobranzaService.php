<?php

namespace App\Services\Cobranza;

use App\Models\Cliente;
use App\Models\CobranzaAlerta;
use App\Models\CobranzaBitacora;
use App\Models\CobranzaFactura;
use App\Models\CobranzaConfiguracion;
use App\Models\User;
use App\Notifications\AlertaPagoLiquidoNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ConfirmarPagoCobranzaService
{
    public function confirmar(CobranzaFactura $factura, ?int $usuarioId = null): array
    {
        if ($factura->pagada) {
            return ['ok' => false, 'motivo' => 'ya_pagada'];
        }

        if (!$factura->pago_pendiente_confirmacion) {
            return ['ok' => false, 'motivo' => 'sin_pendiente'];
        }

        $cliente = $factura->cliente;
        if (!$cliente) {
            return ['ok' => false, 'motivo' => 'sin_cliente'];
        }

        $montoPagado = 0.0;

        DB::transaction(function () use ($factura, $cliente, $usuarioId, &$montoPagado) {
            $facturasActivas = CobranzaFactura::query()
                ->where('cliente_id', $cliente->id)
                ->where('pagada', false)
                ->get();

            $montoPagado = (float) $facturasActivas->sum('monto');

            CobranzaFactura::query()
                ->where('cliente_id', $cliente->id)
                ->where('pagada', false)
                ->update([
                    'pagada' => true,
                    'pago_pendiente_confirmacion' => false,
                    'detectado_en_import_at' => null,
                ]);

            CobranzaAlerta::query()
                ->where('cliente_id', $cliente->id)
                ->where('estado', 'pendiente')
                ->update(['estado' => 'resuelta']);

            $cliente->update([
                'fecha_inicio_credito' => null,
                'alerta_aumento_credito' => false,
            ]);

            CobranzaBitacora::create([
                'cliente_id' => $cliente->id,
                'usuario_id' => $usuarioId ?? auth()->id(),
                'tipo_evento' => 'pago',
                'monto_anterior' => $montoPagado,
                'monto_nuevo' => 0,
                'descripcion' => "Pago confirmado manualmente (factura {$factura->folio}).",
            ]);
        });

        $this->notificarPagoLiquidado($cliente, $montoPagado);

        return ['ok' => true, 'monto_pagado' => $montoPagado];
    }

    public function descartar(CobranzaFactura $factura, ?int $usuarioId = null): array
    {
        if ($factura->pagada) {
            return ['ok' => false, 'motivo' => 'ya_pagada'];
        }

        if (!$factura->pago_pendiente_confirmacion) {
            return ['ok' => false, 'motivo' => 'sin_pendiente'];
        }

        $factura->update([
            'pago_pendiente_confirmacion' => false,
            'detectado_en_import_at' => null,
        ]);

        CobranzaBitacora::create([
            'cliente_id' => $factura->cliente_id,
            'usuario_id' => $usuarioId ?? auth()->id(),
            'tipo_evento' => 'pago_descartado',
            'monto_anterior' => (float) $factura->monto,
            'monto_nuevo' => (float) $factura->monto,
            'descripcion' => "Pago detectado descartado; se mantiene la deuda activa (factura {$factura->folio}).",
        ]);

        return ['ok' => true];
    }

    private function notificarPagoLiquidado(Cliente $cliente, float $montoPagado): void
    {
        $configUsersPagos = Cache::rememberForever('cobranza_config_users_pagos', function () {
            $config = CobranzaConfiguracion::where('llave', 'notified_users_pagos')->first();
            return $config && $config->valor ? json_decode($config->valor, true) : [];
        });

        if (empty($configUsersPagos)) {
            return;
        }

        $usuariosPago = User::whereIn('id', $configUsersPagos)->get();
        if ($usuariosPago->isEmpty()) {
            return;
        }

        Notification::send($usuariosPago, new AlertaPagoLiquidoNotification([
            ['cliente' => $cliente, 'montoPagado' => $montoPagado],
        ]));
    }
}
