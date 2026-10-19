<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Validation\ValidationException;

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
        $modoRevisionMercancia = $pedido->consultaCedisModoRevisionMercancia();
        $esMunicipalSinLogistica = $pedido->esEnvioMunicipalSinLogistica();
        if ($esMunicipalSinLogistica) {
            $omiteCosto = true;
        }

        if ($guiaCliente || $envioPorCobrar) {
            $omiteCosto = true;
        }

        // Tarifa por peso local: no exigir costo hasta tener pesaje, o peso opcional en revisión de mercancía.
        if (! $omiteCosto) {
            $pedido->loadMissing('paqueteria');
            $sinPesoCapturado = $pedido->peso_real_kg === null
                && ($pedido->peso_cobrado_guia_kg === null || (float) $pedido->peso_cobrado_guia_kg <= 0);
            $tarifaPorPesoLocal = $pedido->paqueteria?->esLocalRegional()
                && $pedido->paqueteria->modalidad_tarifa === CatalogoPaqueteriaPedido::MODALIDAD_POR_PESO;
            if ($tarifaPorPesoLocal && (! $tienePesaje || ($modoRevisionMercancia && $sinPesoCapturado))) {
                $omiteCosto = true;
            }
        }

        if ($requiereLogistica && ! $esComplementario && ! $tienePesaje) {
            throw new \InvalidArgumentException(
                $pedido->enRutaPreparacionTienda()
                    ? 'Debe solicitar y recibir la respuesta de Tienda antes de enviar el pedido al auxiliar.'
                    : ($pedido->consultaCedisModoRevisionMercancia()
                        ? 'Debe solicitar y recibir la consulta de mercancía de CEDIS antes de enviar el pedido al auxiliar.'
                        : 'Debe solicitar y recibir el pesaje de CEDIS antes de enviar el pedido al auxiliar.')
            );
        }

        if ($pedido->requiereConsultaCerradaParaProceder() && ! $pedido->consultaCerrada()) {
            throw new \InvalidArgumentException(
                $pedido->enRutaPreparacionTienda()
                    ? 'Debe cerrar la consulta de Tienda (confirmar mercancía con el cliente) antes de enviar el pedido.'
                    : 'Debe cerrar la consulta CEDIS (confirmar mercancía con el cliente) antes de enviar el pedido.'
            );
        }

        $soloResguardos = $pedido->esEnvioSoloResguardos();

        if (! $soloResguardos && empty(trim((string) ($pedido->folio_remision ?? '')))) {
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
        $pedido->loadMissing(['origen', 'tareaPreparacionVigente.modalidad', 'sucursalDestino']);
        if ($pedido->requiereSucursalDestino() && ! $pedido->sucursal_destino_id) {
            $faltantes[] = 'sucursal destino';
        } elseif ($pedido->sucursal_destino_id || $pedido->prohibeSucursalDestino()) {
            try {
                app(ValidarSucursalDestinoPedidoBma::class)->ejecutar(
                    $pedido,
                    $pedido->sucursal_destino_id !== null ? (int) $pedido->sucursal_destino_id : null
                );
            } catch (ValidationException $e) {
                $mensaje = collect($e->errors())->flatten()->first();
                if (is_string($mensaje) && $mensaje !== '') {
                    $faltantes[] = $mensaje;
                }
            }
        }
        // Pedido final: monto obligatorio al enviar (solo se llega aquí con consulta cerrada, salvo complemento).
        if (! $soloResguardos && (float) $pedido->total_mercancia <= 0) {
            $faltantes[] = 'total de mercancía';
        }
        if (! $soloResguardos && ! $pedido->tienePdfPedido()) {
            $faltantes[] = 'PDF o foto del pedido';
        }
        if ($soloResguardos && ! $pedido->tieneResguardosParaEnviar()) {
            $faltantes[] = 'resguardo de GELIA o remisión externa';
        }
        if ($soloResguardos && ! $pedido->tieneAnexoPagoEnvioRegistrado()) {
            $faltantes[] = 'pago de envío';
        }

        if ($requiereLogistica) {
            if ($pedido->enRutaPreparacionTienda() && ! $pedido->esTrasladoInternoAPdv()) {
                $this->validarPesoPreparacionTienda($pedido, $faltantes);
            } elseif ($tienePesaje && ! $modoRevisionMercancia) {
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
            } elseif ($tienePesaje && $modoRevisionMercancia) {
                $pedido->loadMissing('paqueteria');
                if (! $esMunicipalSinLogistica && $pedido->paqueteria?->requiere_caja && ! $pedido->esTrasladoInternoAPdv()) {
                    if (! $pedido->catalogo_tipo_caja_id) {
                        $faltantes[] = 'tipo de caja';
                    }
                    if ($pedido->numero_cajas === null) {
                        $faltantes[] = 'bultos';
                    }
                }
            } elseif (! $omiteCosto && ! $modoRevisionMercancia) {
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

            if (! $guiaCliente && ! $pedido->esTrasladoInternoAPdv()) {
                if (! $esResguardoAbierto && ! $pedido->catalogo_paqueteria_id) {
                    $faltantes[] = 'paquetería';
                }
                if (! $esResguardoAbierto && ! $esMunicipalSinLogistica) {
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

                if (! $omiteCosto && $tienePesaje) {
                    $totalesEnvio = app(CalcularTotalesEnvioPedidoService::class);
                    if ($totalesEnvio->requiereDesgloseCajas($pedido)) {
                        $pedido->loadMissing('cajas');
                        $idx = 0;
                        foreach ($pedido->cajas as $caja) {
                            if (! $caja->estaActiva()) {
                                continue;
                            }
                            $idx++;
                            if (! $caja->tieneDesgloseCosto()) {
                                $faltantes[] = "costo de envío (Envío {$idx})";
                            }
                        }
                    }
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

        if ($pedido->esEnvioSoloResguardos() && ! $pedido->anexoEnvioResguardosAprobado()) {
            return PedidoBma::ESTATUS_ENVIO_PENDIENTE_REVISION_ANEXO;
        }

        if ($pedido->esMunicipioDiferido() && $pedido->costo_envio === null) {
            return PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION;
        }

        return PedidoBma::ESTATUS_ENVIO_COMPLETO;
    }

    /**
     * La respuesta de Tienda no es un pesaje CEDIS. El peso solo aplica si el transporte o la modalidad lo exigen.
     *
     * @param  list<string>  $faltantes
     */
    protected function validarPesoPreparacionTienda(PedidoBma $pedido, array &$faltantes): void
    {
        $pedido->loadMissing(['tareaPreparacionVigente.modalidad', 'tareaPreparacionVigente.paqueteria', 'tareaPreparacionRespondida.modalidad', 'tareaPreparacionRespondida.paqueteria']);
        $tarea = $pedido->tareaPreparacionVigente ?? $pedido->tareaPreparacionRespondida;
        if (! $tarea) {
            return;
        }

        $req = app(CalcularRequisitosPreparacionService::class)->efectivos($tarea);
        if (! empty($req['peso_real_obligatorio'])) {
            $peso = $tarea->peso_real_kg ?? $pedido->peso_real_kg;
            if ($peso === null || $peso === '' || (float) $peso <= 0) {
                $faltantes[] = 'peso real (preparación de Tienda)';
            }
        }
        if (! empty($req['caja_obligatoria']) && ! $tarea->catalogo_tipo_caja_id && ! $pedido->catalogo_tipo_caja_id) {
            $faltantes[] = 'tipo de caja (preparación de Tienda)';
        }
    }
}
