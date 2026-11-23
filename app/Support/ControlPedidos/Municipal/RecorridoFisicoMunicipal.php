<?php

namespace App\Support\ControlPedidos\Municipal;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;

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

    public const MENSAJE_ANEXO_SALIO = 'El paquete ya salió a terminal. Las piezas adicionales van en otro envío.';

    public const MENSAJE_ANEXO_CON_REMISION = 'El pedido ya tiene remisión. Las piezas adicionales se registran como un complemento con su propio pago y remisión.';

    /**
     * Punto único para M-03 (anexo) y M-07 (cambio a Tienda): ¿el envío municipal ya no admite ajustes?
     * - Recorrido municipal 2.0: solo la salida a terminal registrada; el traslado CEDIS→PDV no cuenta.
     * - Históricos, envío con tarea ENVIO_MUNICIPIO y cualquier otro pedido: la regla anterior (`envioYaSalio()`),
     *   que solo se lee y no se modifica, para no alterar M-04, M-09 ni M-10.
     */
    public static function envioYaSalioParaAjustes(PedidoBma $pedido): bool
    {
        if (self::aplica($pedido)) {
            return self::salioATerminal($pedido);
        }

        return PoliticaResguardoEnvio::envioYaSalio($pedido);
    }
}
