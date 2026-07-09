<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBma;

trait ResuelveDatosPedidoBma
{
    protected function resolverPesoVolumetrico(array $datos): ?float
    {
        if (empty($datos['catalogo_tipo_caja_id'])) {
            return null;
        }

        $caja = CatalogoTipoCajaPedido::find($datos['catalogo_tipo_caja_id']);

        return $caja ? (float) $caja->peso_volumetrico : null;
    }

    protected function resolverSaldoFavor(array $datos): float
    {
        $aplica = filter_var($datos['aplica_saldo_favor'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $aplica ? (float) ($datos['saldo_a_favor'] ?? 0) : 0.0;
    }

    protected function resolverEnviaOtraPersona(array $datos): array
    {
        $aplica = filter_var($datos['envia_a_otra_persona'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'envia_a_otra_persona' => $aplica,
            'envia_otra_persona' => $aplica ? ($datos['envia_otra_persona'] ?? null) : null,
        ];
    }

    protected function resolverTotales(array $datos): array
    {
        $mercancia = (float) ($datos['total_mercancia'] ?? 0);
        $envio = (float) ($datos['costo_envio'] ?? 0);
        $aplicaSeguro = filter_var($datos['aplica_seguro'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $costoSeguro = (float) ($datos['costo_seguro'] ?? 0);
        $saldoFavor = $this->resolverSaldoFavor($datos);

        return [
            'total_mercancia' => $mercancia,
            'costo_envio' => $envio,
            'aplica_seguro' => $aplicaSeguro,
            'costo_seguro' => $costoSeguro,
            'saldo_a_favor' => $saldoFavor,
            'total_a_cobrar' => PedidoBma::calcularTotal($mercancia, $envio, $aplicaSeguro, $costoSeguro, $saldoFavor),
        ];
    }

    protected function atributosPedidoBase(array $datos): array
    {
        $totales = $this->resolverTotales($datos);
        $envia = $this->resolverEnviaOtraPersona($datos);

        return array_merge([
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
            'catalogo_almacen_salida_id' => $datos['catalogo_almacen_salida_id'] ?? null,
            'catalogo_banco_id' => $datos['catalogo_banco_id'] ?? null,
            'requiere_factura' => filter_var($datos['requiere_factura'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'catalogo_tipo_caja_id' => $datos['catalogo_tipo_caja_id'] ?? null,
            'numero_cajas' => (int) ($datos['numero_cajas'] ?? 1),
            'peso_real_kg' => $datos['peso_real_kg'] ?? null,
            'peso_volumetrico_kg' => $this->resolverPesoVolumetrico($datos),
            'peso_con_productos_kg' => $datos['peso_con_productos_kg'] ?? null,
            'catalogo_paqueteria_id' => $datos['catalogo_paqueteria_id'] ?? null,
            'catalogo_tipo_guia_id' => $datos['catalogo_tipo_guia_id'] ?? null,
            'catalogo_zona_id' => $datos['catalogo_zona_id'] ?? null,
            'catalogo_envio_tienda_id' => $datos['catalogo_envio_tienda_id'] ?? null,
            'envio_tienda_otro' => $datos['envio_tienda_otro'] ?? null,
            'codigo_postal' => $datos['codigo_postal'] ?? null,
            'domicilio_entrega' => $datos['domicilio_entrega'] ?? null,
            'es_resguardo' => filter_var($datos['es_resguardo'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'comentarios_drive' => $datos['comentarios_drive'] ?? null,
        ], $envia, $totales);
    }
}
