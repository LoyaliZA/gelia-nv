<?php

namespace App\Services\ControlPedidos\Conversion;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use App\Support\ControlPedidos\Conversion\HerenciaConsultaConversion;
use App\Support\ControlPedidos\Conversion\SolicitudConfirmarConversion;

class PersistirConversionModalidad
{
    /**
     * @param  array<string, mixed>  $captura
     * @param  list<int>  $documentosIds
     * @return array<string, mixed>
     */
    public function confirmar(
        PedidoBma $pedido,
        SolicitudConfirmarConversion $solicitud,
        array $captura,
        HerenciaConsultaConversion $herencia,
        int $usuarioId,
        array $documentosIds,
    ): array {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $previa = is_array($json['conversion_modalidad'] ?? null) ? $json['conversion_modalidad'] : [];
        $historial = is_array($previa['historial'] ?? null) ? $previa['historial'] : [];
        if ($previa !== [] && ($previa['estado'] ?? null) !== null) {
            $snapshot = $previa;
            unset($snapshot['historial']);
            $historial[] = $snapshot;
        }

        $version = (int) ($previa['conversion_version'] ?? 0) + 1;
        $json['modalidad_entrega'] = CodigosConversionModalidad::DESTINO_MUNICIPAL;
        $json['codigo_modalidad'] = CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO;
        $json['expediente'] = $captura['expediente'];
        $json['finanzas_municipales'] = [
            'costo_transporte' => $captura['costo'],
            'leyenda_terminal' => $captura['leyenda'],
            'aviso_costo_posterior_at' => $captura['costo'] === 'diferido' ? now()->toIso8601String() : null,
        ];
        if (array_key_exists('almacen_id', $captura) && $captura['almacen_id'] !== null) {
            $json['almacen_revision_id'] = $captura['almacen_id'];
        }

        $t03 = is_array($json['cambio_modalidad_tienda'] ?? null) ? $json['cambio_modalidad_tienda'] : [];
        if (($t03['estado'] ?? null) === CodigosConversionModalidad::ESTADO_T03_PENDIENTE
            && ($t03['destino'] ?? null) === CodigosConversionModalidad::DESTINO_MUNICIPAL) {
            $t03['estado'] = 'conectada';
            $t03['conectada_at'] = now()->toIso8601String();
            $t03['conectada_por_id'] = $usuarioId;
            $t03['conversion_version'] = $version;
            $json['cambio_modalidad_tienda'] = $t03;
        }

        $json['conversion_modalidad'] = [
            'estado' => CodigosConversionModalidad::ESTADO_CONFIRMADA,
            'origen' => CodigosConversionModalidad::ORIGEN_TIENDA,
            'destino' => CodigosConversionModalidad::DESTINO_MUNICIPAL,
            'conversion_version' => $version,
            'clave_idempotencia' => $solicitud->clave_idempotencia,
            'confirmada_at' => now()->toIso8601String(),
            'confirmada_por_id' => $usuarioId,
            'motivo' => $solicitud->motivo,
            'error_captura' => $solicitud->error_captura,
            'documentos_ids' => $documentosIds,
            'herencia' => [
                'caso' => $herencia->caso,
                'procedencia' => $herencia->procedencia,
                'consulta_origen' => $herencia->consulta_origen,
            ],
            'historial' => $historial,
        ];

        $tipo = $captura['costo'] === 'diferido'
            ? CatalogoTipoOperacionEnvio::porCodigo(CatalogoTipoOperacionEnvio::CODIGO_MUNICIPIO_DIFERIDO)
            : null;
        $envio = $captura['costo'] === 'diferido' ? null : $captura['monto'];
        $pedido->fill([
            'solicitud_preparacion_json' => $json,
            'catalogo_paqueteria_id' => $captura['paqueteria']->id,
            'costo_envio' => $envio,
            'total_a_cobrar' => PedidoBma::calcularTotal(
                (float) $pedido->total_mercancia,
                $envio === null ? 0 : (float) $envio,
                (bool) $pedido->aplica_seguro,
                (float) $pedido->costo_seguro,
                (float) $pedido->saldo_a_favor
            ),
            'tipo_operacion_envio_id' => $tipo?->id ?? CatalogoTipoOperacionEnvio::idNormal(),
        ]);
        if ($captura['costo'] === 'diferido') {
            $pedido->estatus_envio = PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION;
        }
        $pedido->save();

        return $json;
    }
}
