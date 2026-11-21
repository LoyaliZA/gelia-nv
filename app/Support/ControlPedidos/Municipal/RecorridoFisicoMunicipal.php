<?php

namespace App\Support\ControlPedidos\Municipal;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\ConsultaMunicipal;

/**
 * Marca del recorrido físico propio de Municipal: CEDIS empaca sin guía ni rastreo,
 * traslada al PDV por el traslado compartido y sale a terminal desde la custodia compartida.
 * Solo los municipales con la marca persistente `recorrido_fisico_municipal` (la escribe el alta/guardado
 * que resolvió una sucursal destino) entran; los anteriores conservan su comportamiento. Un municipal con tarea ENVIO_MUNICIPIO abierta conserva su camino anterior.
 */
final class RecorridoFisicoMunicipal
{
    public static function aplica(PedidoBma $pedido): bool
    {
        if (! ConsultaMunicipal::es($pedido)) {
            return false;
        }
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        if (($json['recorrido_fisico_municipal'] ?? false) !== true) {
            return false;
        }
        if (! $pedido->exists) {
            return true;
        }

        $pedido->loadMissing('tareaPreparacionVigente.modalidad');

        return $pedido->tareaPreparacionVigente?->modalidad?->codigo !== CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO;
    }

    public const MENSAJE_SALIO_A_TERMINAL = 'El paquete ya salió a terminal. El cambio de modalidad ya no está disponible.';

    /**
     * Hecho persistente «ya salió a terminal» (Gerencia PDV con receptor). No se deduce de FASE_ENVIADO:
     * esa fase ya llega con la salida de CEDIS hacia PDV.
     */
    public static function salioATerminal(PedidoBma $pedido): bool
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];

        return is_array($json['salida_terminal'] ?? null);
    }
}
