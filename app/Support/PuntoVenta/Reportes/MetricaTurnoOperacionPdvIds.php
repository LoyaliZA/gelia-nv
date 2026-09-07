<?php

namespace App\Support\PuntoVenta\Reportes;

final class MetricaTurnoOperacionPdvIds
{
    public const T_01_ALTAS = 'T-01';

    public const T_02_ASIGNACIONES = 'T-02';

    public const T_03_TURNOS_CERRADOS = 'T-03';

    public const T_04_BAJAS_COLA = 'T-04';

    public const T_05_ESPERA_COLA = 'T-05';

    public const T_06_ESPERA_INICIAL = 'T-06';

    public const T_07_DURACION_ATENCION = 'T-07';

    public const T_08_CICLO_TOTAL = 'T-08';

    public const T_09_TASA_ABANDONO = 'T-09';

    public const T_10_TASA_NO_SE_PRESENTO = 'T-10';

    public const T_11_TASA_REATENCION = 'T-11';

    public const T_12_TRANSFERENCIAS = 'T-12';

    public const T_13_PRORROGAS = 'T-13';

    public const T_14_DISTRIBUCION_PRIORIDAD = 'T-14';

    public const T_15_PERCENTILES_ESPERA_COLA = 'T-15';

    public const T_16_PERCENTILES_DURACION_ATENCION = 'T-16';

    public const O_01_JORNADAS_ABIERTAS = 'O-01';

    public const O_02_DURACION_JORNADA = 'O-02';

    public const O_03_TIEMPO_PAUSA = 'O-03';

    public const O_04_TIEMPO_DISPONIBLE = 'O-04';

    public const O_05_TIEMPO_ATENCION_INTERVALOS = 'O-05';

    public const O_06_OCUPACION = 'O-06';

    public const O_07_DISPONIBILIDAD = 'O-07';

    public const O_08_DIAS_SIN_ALTAS = 'O-08';

    public const O_09_CIERRES_HORARIO = 'O-09';

    public const O_10_AMPLIACIONES_HORARIO = 'O-10';

    public const O_11_ALTAS_DESPUES_CIERRE = 'O-11';

    public const UMBRAL_PERCENTILES = 30;

    public const ALCANCE_PROPIO = 'propio';

    public const ALCANCE_EQUIPO = 'equipo';

    public const ALCANCE_GLOBAL = 'global';

    /**
     * @return list<string>
     */
    public static function metricasTurnos(): array
    {
        return [
            self::T_01_ALTAS,
            self::T_02_ASIGNACIONES,
            self::T_03_TURNOS_CERRADOS,
            self::T_04_BAJAS_COLA,
            self::T_05_ESPERA_COLA,
            self::T_06_ESPERA_INICIAL,
            self::T_07_DURACION_ATENCION,
            self::T_08_CICLO_TOTAL,
            self::T_09_TASA_ABANDONO,
            self::T_10_TASA_NO_SE_PRESENTO,
            self::T_11_TASA_REATENCION,
            self::T_12_TRANSFERENCIAS,
            self::T_13_PRORROGAS,
            self::T_14_DISTRIBUCION_PRIORIDAD,
            self::T_15_PERCENTILES_ESPERA_COLA,
            self::T_16_PERCENTILES_DURACION_ATENCION,
        ];
    }

    /**
     * @return list<string>
     */
    public static function metricasOperacion(): array
    {
        return [
            self::O_01_JORNADAS_ABIERTAS,
            self::O_02_DURACION_JORNADA,
            self::O_03_TIEMPO_PAUSA,
            self::O_04_TIEMPO_DISPONIBLE,
            self::O_05_TIEMPO_ATENCION_INTERVALOS,
            self::O_06_OCUPACION,
            self::O_07_DISPONIBILIDAD,
            self::O_08_DIAS_SIN_ALTAS,
            self::O_09_CIERRES_HORARIO,
            self::O_10_AMPLIACIONES_HORARIO,
            self::O_11_ALTAS_DESPUES_CIERRE,
        ];
    }
}
