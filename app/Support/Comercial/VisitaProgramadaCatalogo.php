<?php

namespace App\Support\Comercial;

use App\Models\Comercial\VisitaClienteProgramada;

final class VisitaProgramadaCatalogo
{
    /**
     * @return list<array{valor: string, etiqueta: string}>
     */
    public static function intenciones(): array
    {
        return [
            ['valor' => VisitaClienteProgramada::INTENCION_CONFIRMO, 'etiqueta' => 'Confirmó asistencia'],
            ['valor' => VisitaClienteProgramada::INTENCION_POSIBLE, 'etiqueta' => 'Posible asistencia'],
        ];
    }

    public static function etiquetaIntencion(string $valor): string
    {
        foreach (self::intenciones() as $item) {
            if ($item['valor'] === $valor) {
                return $item['etiqueta'];
            }
        }

        return $valor;
    }

    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            VisitaClienteProgramada::ESTADO_PROGRAMADA => 'Programada',
            VisitaClienteProgramada::ESTADO_ASISTIO => 'Asistió',
            VisitaClienteProgramada::ESTADO_NO_ASISTIO => 'No asistió',
            default => $estado,
        };
    }
}
