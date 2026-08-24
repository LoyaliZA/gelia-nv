<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use Illuminate\Validation\ValidationException;

final class MaquinaEstadosTareaPreparacion
{
    /** @var array<string, list<string>> */
    private const TRANSICIONES = [
        PedidoBmaTareaPreparacion::ESTADO_PENDIENTE => [
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
            PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
        ],
        PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION => [
            PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
            PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
        ],
        PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA => [
            PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
        ],
        PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA => [
            PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
            PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
        ],
        PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO => [
            PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
            PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
            PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS,
            PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
            PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
        ],
        PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA => [
            PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
            PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
            PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
        ],
        PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO => [
            PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
            PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS,
        ],
        PedidoBmaTareaPreparacion::ESTADO_RECHAZADA_CEDIS => [
            PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
            PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
        ],
        PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA => [
            PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
            PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
        ],
        PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS => [],
        PedidoBmaTareaPreparacion::ESTADO_LIBERADA => [],
        PedidoBmaTareaPreparacion::ESTADO_CANCELADA => [],
    ];

    /** @var array<string, string> */
    private const PERMISOS = [
        PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION => 'control_pedidos.tienda.tomar',
        PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA => 'control_pedidos.tienda.responder',
        PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO => 'control_pedidos.tienda.responder',
        PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA => 'control_pedidos.tienda.responder',
        PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO => 'control_pedidos.tienda.trasladar',
        PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA => 'control_pedidos.tienda.reportar_error',
        PedidoBmaTareaPreparacion::ESTADO_LIBERADA => 'control_pedidos.tienda.liberar',
        PedidoBmaTareaPreparacion::ESTADO_CANCELADA => 'control_pedidos.preparacion.corregir',
        // Recepción CEDIS usa permisos del módulo Traspasos; sync interno no exige permiso Tienda.
        // Colocación de carátula: permiso control_pedidos.tienda.confirmar_caratula en ruta/servicio.
    ];

    public static function puedeTransicionar(string $origen, string $destino): bool
    {
        if ($origen === $destino) {
            return true;
        }

        return in_array($destino, self::TRANSICIONES[$origen] ?? [], true);
    }

    public static function assertTransicion(string $origen, string $destino): void
    {
        if (! self::puedeTransicionar($origen, $destino)) {
            throw ValidationException::withMessages([
                'estado' => 'La tarea ya no está en el estado esperado. Actualice la página e intente de nuevo.',
            ]);
        }
    }

    public static function permisoParaDestino(string $destino): ?string
    {
        return self::PERMISOS[$destino] ?? null;
    }
}
