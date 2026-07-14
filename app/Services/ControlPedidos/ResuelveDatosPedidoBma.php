<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEnvioTienda;
use App\Models\ControlPedidos\CatalogoOrigenPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
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
        $envio = isset($datos['costo_envio']) && $datos['costo_envio'] !== '' && $datos['costo_envio'] !== null
            ? (float) $datos['costo_envio']
            : 0.0;
        $saldoFavor = $this->resolverSaldoFavor($datos);
        $seguro = $this->resolverSeguro($datos, $mercancia, $envio);

        return [
            'total_mercancia' => $mercancia,
            'costo_envio' => isset($datos['costo_envio']) && $datos['costo_envio'] !== '' && $datos['costo_envio'] !== null
                ? $envio
                : null,
            'aplica_seguro' => $seguro['aplica_seguro'],
            'costo_seguro' => $seguro['costo_seguro'],
            'saldo_a_favor' => $saldoFavor,
            'total_a_cobrar' => PedidoBma::calcularTotal(
                $mercancia,
                $envio,
                $seguro['aplica_seguro'],
                $seguro['costo_seguro'],
                $saldoFavor
            ),
        ];
    }

    protected function resolverSeguro(array $datos, float $mercancia, float $envio): array
    {
        $paqueteriaId = $datos['catalogo_paqueteria_id'] ?? null;
        if (!$paqueteriaId) {
            return ['aplica_seguro' => false, 'costo_seguro' => 0.0];
        }

        $paqueteria = CatalogoPaqueteriaPedido::find($paqueteriaId);
        $calc = app(CalcularSeguroPedidoService::class);
        $costo = $calc->calcularCosto($paqueteria?->nombre, $envio, $mercancia);
        $aplicaSeguro = $calc->tieneCobertura($paqueteria?->nombre)
            && filter_var($datos['aplica_seguro'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'aplica_seguro' => $aplicaSeguro,
            'costo_seguro' => $costo,
        ];
    }

    protected function resolverEnvioTiendaDesdeOrigen(array $datos): array
    {
        $origen = ! empty($datos['origen_id'])
            ? CatalogoOrigenPedido::find($datos['origen_id'])
            : null;

        if (! $origen) {
            return [
                'catalogo_envio_tienda_id' => null,
                'envio_tienda_otro' => null,
            ];
        }

        $termino = $origen->requiere_logistica ? 'Envío' : 'Tienda';

        $match = CatalogoEnvioTienda::query()
            ->where('activo', true)
            ->where('es_otro', false)
            ->where(function ($q) use ($termino, $origen) {
                $q->where('nombre', $termino)
                    ->orWhere('nombre', 'like', $termino.'%');
                if ($origen->requiere_logistica) {
                    $q->orWhere('nombre', 'like', 'Envio%');
                }
            })
            ->orderBy('nombre')
            ->first();

        return [
            'catalogo_envio_tienda_id' => $match?->id,
            'envio_tienda_otro' => null,
        ];
    }

    protected function atributosPedidoBase(array $datos): array
    {
        $totales = $this->resolverTotales($datos);
        $envia = $this->resolverEnviaOtraPersona($datos);
        $envioTienda = $this->resolverEnvioTiendaDesdeOrigen($datos);

        return array_merge([
            'folio_remision' => isset($datos['folio_remision']) && trim((string) $datos['folio_remision']) !== ''
                ? trim((string) $datos['folio_remision'])
                : null,
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
            'origen_id' => $datos['origen_id'] ?? null,
            'almacen_id' => $datos['almacen_id'] ?? null,
            'catalogo_banco_id' => $datos['catalogo_banco_id'] ?? null,
            'catalogo_tipo_caja_id' => $datos['catalogo_tipo_caja_id'] ?? null,
            'numero_cajas' => isset($datos['numero_cajas']) && $datos['numero_cajas'] !== ''
                ? (int) $datos['numero_cajas']
                : null,
            'peso_real_kg' => $datos['peso_real_kg'] ?? null,
            'peso_volumetrico_kg' => $this->resolverPesoVolumetrico($datos),
            'peso_cobrado_guia_kg' => $datos['peso_cobrado_guia_kg'] ?? null,
            'catalogo_paqueteria_id' => $datos['catalogo_paqueteria_id'] ?? null,
            'catalogo_tipo_guia_id' => $datos['catalogo_tipo_guia_id'] ?? null,
            'catalogo_zona_id' => $datos['catalogo_zona_id'] ?? null,
            'codigo_postal' => $datos['codigo_postal'] ?? null,
            'domicilio_entrega' => $datos['domicilio_entrega'] ?? null,
            'cliente_direccion_id' => isset($datos['cliente_direccion_id']) && $datos['cliente_direccion_id'] !== ''
                ? (int) $datos['cliente_direccion_id']
                : null,
            'es_resguardo' => filter_var($datos['es_resguardo'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'anexar_remision' => filter_var($datos['anexar_remision'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'comentarios_drive' => $datos['comentarios_drive'] ?? null,
        ], $envioTienda, $envia, $totales);
    }
}
