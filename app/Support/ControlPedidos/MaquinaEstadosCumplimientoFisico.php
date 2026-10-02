<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use Illuminate\Validation\ValidationException;

final class MaquinaEstadosCumplimientoFisico
{
    /** @var array<string, list<string>> */
    private const TRANSICIONES = [
        PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR => [
            PedidoBmaCumplimientoFisico::ESTADO_SEPARADA,
            PedidoBmaCumplimientoFisico::ESTADO_INCIDENCIA,
        ],
        PedidoBmaCumplimientoFisico::ESTADO_SEPARADA => [
            PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA,
            PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE,
            PedidoBmaCumplimientoFisico::ESTADO_INCIDENCIA,
        ],
        PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA => [
            PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA,
            PedidoBmaCumplimientoFisico::ESTADO_EMPACADA,
            PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE,
            PedidoBmaCumplimientoFisico::ESTADO_INCIDENCIA,
        ],
        PedidoBmaCumplimientoFisico::ESTADO_EMPACADA => [
            PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA,
            PedidoBmaCumplimientoFisico::ESTADO_INCIDENCIA,
        ],
        PedidoBmaCumplimientoFisico::ESTADO_INCIDENCIA => [
            PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR,
            PedidoBmaCumplimientoFisico::ESTADO_SEPARADA,
            PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE,
        ],
        PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE => [
            PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL,
        ],
        PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA => [],
        PedidoBmaCumplimientoFisico::ESTADO_DESPACHADA => [],
        PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL => [],
    ];

    public static function puedeTransicionar(string $origen, string $destino): bool
    {
        return in_array($destino, self::TRANSICIONES[$origen] ?? [], true);
    }

    public static function assertTransicion(string $origen, string $destino): void
    {
        if (! self::puedeTransicionar($origen, $destino)) {
            throw ValidationException::withMessages([
                'estado' => 'El apartado físico ya no está en el estado esperado. Actualice la página e intente de nuevo.',
            ]);
        }
    }
}
