<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;

trait ValidacionCamposPedidoBma
{
    protected function validarCamposRequeridos(PedidoBma $pedido): void
    {
        $pedido->loadMissing(['origen', 'tipoOperacionEnvio']);

        $faltantes = [];
        $requiereLogistica = $pedido->origen?->requiere_logistica ?? true;
        $esDiferido = $pedido->esMunicipioDiferido();
        $esResguardoAbierto = $pedido->esResguardoAbierto();
        $esComplementario = $pedido->esResguardoComplementario();
        $omitePesoCajasCosto = $esDiferido || $esResguardoAbierto || $esComplementario;

        if (empty(trim((string) ($pedido->folio_remision ?? '')))) {
            $faltantes[] = 'folio de pedido';
        }
        if (!$pedido->cliente_id) {
            $faltantes[] = 'cliente';
        }
        if (!$pedido->origen_id) {
            $faltantes[] = 'origen del pedido';
        }
        if (!$pedido->catalogo_banco_id) {
            $faltantes[] = 'banco';
        }
        if (!$omitePesoCajasCosto && $pedido->peso_real_kg === null) {
            $faltantes[] = 'peso real';
        }
        if (!$pedido->almacen_id) {
            $faltantes[] = 'almacén de salida';
        }
        if ($pedido->total_mercancia <= 0) {
            $faltantes[] = 'total de mercancía';
        }
        if ($pedido->documentos()->count() === 0) {
            $faltantes[] = 'comprobante de pago';
        }

        if ($requiereLogistica) {
            if (!$pedido->catalogo_tipo_caja_id) {
                $faltantes[] = 'tipo de caja';
            }
            if (!$omitePesoCajasCosto && $pedido->numero_cajas === null) {
                $faltantes[] = 'número de cajas';
            }
            if (!$pedido->catalogo_tipo_guia_id) {
                $faltantes[] = 'tipo de guía';
            }
            if (!$pedido->catalogo_paqueteria_id) {
                $faltantes[] = 'paquetería';
            }
            if (!$pedido->catalogo_zona_id) {
                $faltantes[] = 'reexpedición';
            }
            if (!$omitePesoCajasCosto && $pedido->costo_envio === null) {
                $faltantes[] = 'costo de envío';
            }
            if (empty($pedido->codigo_postal)) {
                $faltantes[] = 'código postal';
            }
            if (empty($pedido->domicilio_entrega)) {
                $faltantes[] = 'domicilio de entrega';
            }
        }

        if (!empty($faltantes)) {
            throw new \InvalidArgumentException('Complete los campos requeridos: ' . implode(', ', $faltantes) . '.');
        }
    }

    protected function resolverEstatusEnvioAlEnviar(PedidoBma $pedido): string
    {
        $pedido->loadMissing('tipoOperacionEnvio');

        if ($pedido->esResguardoAbierto()) {
            return PedidoBma::ESTATUS_ENVIO_PENDIENTE_LIBERACION;
        }

        if ($pedido->esMunicipioDiferido() && $pedido->costo_envio === null) {
            return PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION;
        }

        return PedidoBma::ESTATUS_ENVIO_COMPLETO;
    }
}
