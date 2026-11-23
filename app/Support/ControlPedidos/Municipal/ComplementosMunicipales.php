<?php

namespace App\Support\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Collection;

/**
 * M-03b: piezas adicionales de un envío municipal que ya tiene remisión. El complemento es un pedido propio
 * (pago y remisión propios) vinculado al principal con `pedido_principal_id` y la marca `complemento_municipal`.
 * No usa la semántica de resguardo de envío (`es_resguardo`, `espera_complemento`) ni los complementos de Tienda/Nacional.
 */
final class ComplementosMunicipales
{
    public const CLAVE = 'complemento_municipal';

    public const CLAVE_PRINCIPAL = 'complementos_municipales';

    public const ESTADO_ABIERTO = 'abierto';

    public const ESTADO_INTEGRADO = 'integrado';

    public const MENSAJE_ABIERTO = 'El envío tiene piezas adicionales sin integrar. No puede salir a terminal hasta integrarlas o resolverlas.';

    public static function es(PedidoBma $pedido): bool
    {
        return $pedido->pedido_principal_id !== null && is_array(self::marca($pedido));
    }

    /** @return array<string, mixed>|null */
    public static function marca(PedidoBma $pedido): ?array
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];

        return is_array($json[self::CLAVE] ?? null) ? $json[self::CLAVE] : null;
    }

    public static function estado(PedidoBma $complemento): ?string
    {
        return self::marca($complemento)['estado'] ?? null;
    }

    /** Complementos del principal que todavía no se integran (un complemento cancelado queda resuelto). */
    public static function abiertos(PedidoBma $principal): Collection
    {
        return PedidoBma::query()
            ->where('pedido_principal_id', $principal->id)
            ->whereNull('cancelado_at')
            ->where('solicitud_preparacion_json->'.self::CLAVE.'->estado', self::ESTADO_ABIERTO)
            ->orderBy('id')
            ->get();
    }

    public static function hayAbiertos(PedidoBma $principal): bool
    {
        return $principal->exists && self::abiertos($principal)->isNotEmpty();
    }
}
