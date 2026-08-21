<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
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
        $omiteCosto = $esDiferido || $esResguardoAbierto || $esComplementario;
        $guiaCliente = (bool) $pedido->cliente_proporciona_guia;
        $envioPorCobrar = (bool) $pedido->envio_por_cobrar;
        $tienePesaje = $pedido->tienePesajeRespondido();

        if ($guiaCliente || $envioPorCobrar) {
            $omiteCosto = true;
        }

        // Tarifa por peso local: no exigir costo hasta tener pesaje.
        if (! $omiteCosto && ! $tienePesaje) {
            $pedido->loadMissing('paqueteria');
            if ($pedido->paqueteria?->esLocalRegional()
                && $pedido->paqueteria->modalidad_tarifa === CatalogoPaqueteriaPedido::MODALIDAD_POR_PESO) {
                $omiteCosto = true;
            }
        }

        if ($requiereLogistica && ! $esComplementario && ! $tienePesaje) {
            throw new \InvalidArgumentException(
                'Debe solicitar y recibir el pesaje de CEDIS antes de enviar el pedido al auxiliar.'
            );
        }

                        if ($pedido->requiereConsultaCerradaParaProceder() && ! $pedido->consultaCerrada()) {
            throw new \InvalidArgumentException(
                'Debe cerrar la consulta CEDIS (confirmar mercancía con el cliente) antes de enviar el pedido.'
            );
        }

        if (empty(trim((string) ($pedido->folio_remision ?? '')))) {
            $faltantes[] = 'folio de pedido';
        }
        if (! $pedido->cliente_id) {
            $faltantes[] = 'cliente';
        }
        if (! $pedido->origen_id) {
            $faltantes[] = 'origen del pedido';
        }
        if (! $pedido->almacen_id) {
            $faltantes[] = 'almacén de salida';
        }
        // Pedido final: monto obligatorio al enviar (solo se llega aquí con consulta cerrada, salvo complemento).
        if ((float) $pedido->total_mercancia <= 0) {
            $faltantes[] = 'total de mercancía';
        }
        if (! $pedido->tienePdfPedido()) {
            $faltantes[] = 'PDF o foto del pedido';
        }

        if ($requiereLogistica) {
            if ($tienePesaje) {
                if ($pedido->peso_real_kg === null) {
                    $faltantes[] = 'peso real (pesaje CEDIS)';
                }
                if (! $pedido->catalogo_tipo_caja_id) {
                    $faltantes[] = 'tipo de caja (pesaje CEDIS)';
                }
                if ($pedido->numero_cajas === null) {
                    $faltantes[] = 'número de cajas (pesaje CEDIS)';
                }

                $pedido->loadMissing('cajas');
                if ($pedido->cajas->isEmpty()) {
                    $faltantes[] = 'detalle de envíos (pesaje CEDIS)';
                } else {
                    foreach ($pedido->cajas as $idx => $caja) {
                        $n = $idx + 1;
                        if (! $caja->catalogo_tipo_caja_id) {
                            $faltantes[] = "tipo de caja (Envío {$n})";
                        }
                        if ($caja->peso_real_kg === null) {
                            $faltantes[] = "peso real (Envío {$n})";
                        }
                        if ($caja->peso_volumetrico_kg === null) {
                            $faltantes[] = "peso volumétrico (Envío {$n})";
                        }
                    }
                }
            } elseif (! $omiteCosto) {
                if ($pedido->peso_real_kg === null) {
                    $faltantes[] = 'peso real';
                }
                if (! $pedido->catalogo_tipo_caja_id) {
                    $faltantes[] = 'tipo de caja';
                }
                if ($pedido->numero_cajas === null) {
                    $faltantes[] = 'número de cajas';
                }
            }

            if (! $guiaCliente) {
                if (! $pedido->catalogo_paqueteria_id) {
                    $faltantes[] = 'paquetería';
                }
                if (! $esResguardoAbierto) {
                    if (! $pedido->catalogo_tipo_guia_id) {
                        $faltantes[] = 'tipo de guía';
                    }
                    if (! $pedido->catalogo_zona_id) {
                        $faltantes[] = 'reexpedición';
                    }
                    if (empty($pedido->codigo_postal)) {
                        $faltantes[] = 'código postal';
                    }
                    if (empty($pedido->domicilio_entrega)) {
                        $faltantes[] = 'domicilio de entrega';
                    }
                }
                if (! $omiteCosto && $pedido->costo_envio === null) {
                    $faltantes[] = 'costo de envío';
                }
            }
        }

        if (! empty($faltantes)) {
            throw new \InvalidArgumentException('Complete los campos requeridos: '.implode(', ', $faltantes).'.');
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
