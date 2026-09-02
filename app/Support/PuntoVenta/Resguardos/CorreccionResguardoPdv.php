<?php

namespace App\Support\PuntoVenta\Resguardos;

final class CorreccionResguardoPdv
{
    public const TIPO_SNAPSHOT_RESGUARDO = 'snapshot_resguardo';

    public const TIPO_ANOTACION_EVENTO = 'anotacion_evento';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return [
            self::TIPO_SNAPSHOT_RESGUARDO,
            self::TIPO_ANOTACION_EVENTO,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            self::TIPO_SNAPSHOT_RESGUARDO => 'Corrección de snapshot del resguardo',
            self::TIPO_ANOTACION_EVENTO => 'Anotación compensatoria sobre evento',
        ];
    }
}
