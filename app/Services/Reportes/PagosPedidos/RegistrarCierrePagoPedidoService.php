<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\ControlPedidos\CalcularTotalesEnvioPedidoService;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;

class RegistrarCierrePagoPedidoService
{
    public function __construct(
        private CoberturaPagoPedidoBmaService $cobertura,
        private CalcularTotalesEnvioPedidoService $totalesEnvio,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $origen = PedidoBmaCierrePago::ORIGEN_FLUJO): PedidoBmaCierrePago
    {
        $pedido->loadMissing(['vendedor.departamento', 'cliente', 'almacen', 'origen', 'estatus']);

        $resumen = $this->cobertura->calcular($pedido);
        $totales = $this->totalesEnvio->calcular($pedido->loadMissing(['cajas']));
        $aplicaSeguro = (bool) $pedido->aplica_seguro;
        $montoSeguro = $aplicaSeguro ? (string) ($totales['costo_seguro'] ?? '0.00') : '0.00';
        $montoEnvio = (string) ($totales['costo_para_cobertura'] ?? '0.00');
        $montoVenta = number_format((float) ($pedido->total_mercancia ?? 0), 2, '.', '');

        $version = (int) PedidoBmaCierrePago::query()
            ->where('pedido_bma_id', $pedido->id)
            ->max('version') + 1;

        PedidoBmaCierrePago::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('estado', PedidoBmaCierrePago::ESTADO_VIGENTE)
            ->update([
                'estado' => PedidoBmaCierrePago::ESTADO_REVOCADO,
                'revocado_at' => now(),
                'revocado_por_id' => $usuarioId,
                'motivo_revocacion' => 'Sustituido por nueva validación (v'.$version.')',
            ]);

        $validadoAt = $pedido->pago_validado_at ?? now();
        $departamentoId = $pedido->vendedor?->departamento_id
            ?? $pedido->vendedor?->departamentoParaBranding()?->id;

        $remisionVigente = $pedido->documentos()
            ->where('tipo', PedidoBmaDocumento::TIPO_REMISION)
            ->vigente()
            ->first();

        $cierre = PedidoBmaCierrePago::query()->create([
            'pedido_bma_id' => $pedido->id,
            'version' => $version,
            'estado' => PedidoBmaCierrePago::ESTADO_VIGENTE,
            'origen' => $origen,
            'pedido_fecha' => $pedido->fecha,
            'validado_at' => $validadoAt,
            'validado_por_id' => $pedido->pago_validado_por_id ?? $usuarioId,
            'monto_venta' => $montoVenta,
            'monto_envio' => $montoEnvio,
            'monto_seguro' => $montoSeguro,
            'total_pedido' => $resumen['total_a_cubrir'],
            'saf_aplicado' => $resumen['saldo_favor_aplicado'],
            'total_a_cobrar' => $resumen['total_a_cobrar'],
            'pagos_validos' => $resumen['pagos_validos'],
            'diferencia' => $resumen['diferencia'],
            'excedente' => $resumen['excedente_generado'],
            'tolerancia_aplicada' => $resumen['tolerancia_aplicada'],
            'estado_cobertura' => $resumen['cobertura'],
            'folio_snapshot' => $pedido->folio,
            'folio_remision_snapshot' => $pedido->folio_remision,
            'cliente_id' => $pedido->cliente_id,
            'vendedor_id' => $pedido->vendedor_id,
            'departamento_id' => $departamentoId,
            'almacen_id' => $pedido->almacen_id,
            'metadata_snapshot' => [
                'origen' => $pedido->origen?->nombre,
                'estatus' => $pedido->estatus?->nombre_visual,
                'remision_documento_id' => $remisionVigente?->id,
                'remision_nombre' => $remisionVigente?->nombre_original,
            ],
        ]);

        $pagos = PedidoBmaPago::query()
            ->where('pedido_bma_id', $pedido->id)
            ->with('banco')
            ->orderBy('numero_exhibicion')
            ->get();

        foreach ($pagos as $pago) {
            PedidoBmaCierrePagoItem::query()->create([
                'pedido_bma_cierre_pago_id' => $cierre->id,
                'pedido_bma_pago_id' => $pago->id,
                'numero_exhibicion' => $pago->numero_exhibicion,
                'monto_snapshot' => $pago->monto,
                'forma_pago_snapshot' => $pago->forma_pago,
                'catalogo_banco_id' => $pago->catalogo_banco_id,
                'banco_snapshot' => $pago->banco?->nombre,
                'referencia_snapshot' => $pago->referencia,
                'fecha_pago_snapshot' => $pago->fecha_pago,
                'estado_revision_snapshot' => $pago->estado_revision,
                'activo_para_cobertura_snapshot' => $pago->activo_para_cobertura,
                'nombre_archivo_snapshot' => $pago->nombre_original,
                'mime_type_snapshot' => $pago->mime_type,
                'tamano_bytes_snapshot' => $pago->tamano_bytes,
                'ruta_archivo_snapshot' => $pago->ruta_archivo,
                'reemplaza_pago_id' => $pago->reemplaza_pago_id,
                'capturado_por_id' => $pago->capturado_por_id,
                'capturado_at_snapshot' => $pago->created_at,
                'revisado_por_id' => $pago->revisado_por_id,
                'revisado_at_snapshot' => $pago->revisado_at,
                'motivo_rechazo_snapshot' => $pago->motivo_rechazo,
            ]);
        }

        return $cierre->load('items');
    }
}
