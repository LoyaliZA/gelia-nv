<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Support\ControlPedidos\TransferenciaT02;
use Illuminate\Support\Facades\DB;

/** Veinte días naturales generan gestión; nunca cancelan ni liberan mercancía pagada. */
final class SeguimientoRecoleccionT02Service
{
    public function ejecutar(): int
    {
        $avisados = 0;
        PedidoBma::query()->whereNull('cancelado_at')->whereNotNull('numero_remision')
            ->where('solicitud_preparacion_json->modalidad_entrega', 'tienda')
            ->where('solicitud_preparacion_json->cobertura_tienda->tipo', 'transferencia')
            ->chunkById(100, function ($pedidos) use (&$avisados) {
                foreach ($pedidos as $pedido) {
                    if (! TransferenciaT02::aplica($pedido)) continue;
                    $avisados += DB::transaction(function () use ($pedido) {
                        $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
                        $json = $pedido->solicitud_preparacion_json ?? [];
                        if (isset($json['seguimiento_recoleccion']['avisado_at']) || $pedido->cancelado_at
                            || $pedido->estatus?->fase_ciclo === \App\Models\ControlPedidos\CatalogoEstatusPedido::FASE_ENTREGADO
                            || ResguardoPdv::query()->where('pedido_bma_id', $pedido->id)->where('estado', ResguardoPdv::ESTADO_ENTREGADO)->exists()) return 0;
                        $remision = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->oldest()->first();
                        $desde = isset($json['remision_tienda']['emitida_at']) ? \Carbon\Carbon::parse($json['remision_tienda']['emitida_at']) : $remision?->created_at;
                        if (! $desde || now()->lt($desde->copy()->addDays(20))) return 0;
                        $json['seguimiento_recoleccion'] = ['estado' => 'pendiente_gestion', 'desde_remision' => $desde->toIso8601String(),
                            'limite' => $desde->copy()->addDays(20)->toIso8601String(), 'avisado_at' => now()->toIso8601String()];
                        $pedido->update(['solicitud_preparacion_json' => $json]);
                        app(NotificarPedidoBmaService::class)->ejecutar($pedido, 'pedido_custodia_20_dias',
                            'Pasaron 20 días naturales desde la remisión. Recolección pendiente de gestión; la mercancía y el pago se conservan.', [], null, true);

                        return 1;
                    });
                }
            });

        return $avisados;
    }
}
