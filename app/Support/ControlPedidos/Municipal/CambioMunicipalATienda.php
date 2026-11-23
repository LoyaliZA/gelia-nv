<?php

namespace App\Support\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;

/**
 * Cambio de un envío municipal a recolección en tienda. Solicitud, aplicación (verificación del área que tiene el
 * paquete), salida y entrega son hechos distintos. Mientras el cambio esté abierto o aplicado el paquete no sale a terminal.
 */
final class CambioMunicipalATienda
{
    public const CLAVE = 'cambio_a_tienda';

    public const ESTADO_SOLICITADO = 'solicitado';

    public const ESTADO_APLICADO = 'aplicado';

    public const RELACION_EVIDENCIA = 'cambio_tienda_peticion_cliente';

    public const MENSAJE_SALIDA = 'El cliente pasará a recoger este paquete en la sucursal. No sale a terminal.';

    public const MENSAJE_VERIFICACION = 'Falta que el área que tiene el paquete verifique el cambio a recolección en sucursal.';

    /** @return array<string, mixed>|null */
    public static function datos(PedidoBma $pedido): ?array
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];

        return is_array($json[self::CLAVE] ?? null) ? $json[self::CLAVE] : null;
    }

    public static function estado(PedidoBma $pedido): ?string
    {
        return self::datos($pedido)['estado'] ?? null;
    }

    /** Solicitado o aplicado: el paquete ya no sigue el camino a terminal. */
    public static function activo(PedidoBma $pedido): bool
    {
        return in_array(self::estado($pedido), [self::ESTADO_SOLICITADO, self::ESTADO_APLICADO], true);
    }

    public static function pendienteDeVerificacion(PedidoBma $pedido): bool
    {
        return self::estado($pedido) === self::ESTADO_SOLICITADO;
    }
}
