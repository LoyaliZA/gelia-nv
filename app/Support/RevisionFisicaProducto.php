<?php

namespace App\Support;

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;

/**
 * Contrato compartido de revisión física (control pedidos + traspasos).
 */
final class RevisionFisicaProducto
{
    public const ESTADO_BUENO = PedidoBmaRevisionProducto::ESTADO_BUENO;

    public const ESTADOS = PedidoBmaRevisionProducto::ESTADOS;

    public const LABELS = PedidoBmaRevisionProducto::LABELS;

    public static function requiereEvidencia(string $estado): bool
    {
        return PedidoBmaRevisionProducto::requiereEvidencia($estado);
    }

    public static function requiereComentario(string $estado): bool
    {
        return PedidoBmaRevisionProducto::requiereComentario($estado);
    }

    /**
     * @param  list<string>  $estados
     */
    public static function derivarEstadoGeneral(array $estados, string $default = self::ESTADO_BUENO): string
    {
        if ($estados === []) {
            return $default;
        }

        $prioridad = [
            PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA => 5,
            PedidoBmaRevisionProducto::ESTADO_DANADO => 4,
            PedidoBmaRevisionProducto::ESTADO_MALO => 3,
            PedidoBmaRevisionProducto::ESTADO_REGULAR => 2,
            PedidoBmaRevisionProducto::ESTADO_BUENO => 1,
        ];

        $peor = $default;
        $peorPeso = $prioridad[$default] ?? 0;

        foreach ($estados as $estado) {
            $peso = $prioridad[$estado] ?? 0;
            if ($peso > $peorPeso) {
                $peorPeso = $peso;
                $peor = $estado;
            }
        }

        return $peor;
    }
}
