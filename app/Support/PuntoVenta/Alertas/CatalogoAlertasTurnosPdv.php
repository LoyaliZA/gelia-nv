<?php

namespace App\Support\PuntoVenta\Alertas;

use App\Models\PuntoVenta\TurnoPdvEvento;

/**
 * Catálogo central de tipos audibles de turnos para terminal de sucursal.
 * Los guiones definitivos viven aquí; el frontend consume prioridad y metadatos vía API/config.
 */
final class CatalogoAlertasTurnosPdv
{
    public const PRIORIDAD_NORMAL = 'normal';

    public const PRIORIDAD_ALTA = 'alta';

    public const PRIORIDAD_CRITICA = 'critica';

    /**
     * @return array<string, array{prioridad: string, tono_configurable: bool, guion: string}>
     */
    public static function definiciones(): array
    {
        return [
            TurnoPdvEvento::TIPO_ALTA => [
                'prioridad' => self::PRIORIDAD_NORMAL,
                'tono_configurable' => true,
                'guion' => 'Nuevo turno {folio}',
            ],
            TurnoPdvEvento::TIPO_ASIGNADO => [
                'prioridad' => self::PRIORIDAD_ALTA,
                'tono_configurable' => false,
                'guion' => 'Turno {folio}, pasar con {vendedor}',
            ],
            TurnoPdvEvento::TIPO_REATENCION => [
                'prioridad' => self::PRIORIDAD_ALTA,
                'tono_configurable' => false,
                'guion' => 'Re-atención {folio}, pasar con {vendedor}',
            ],
            TurnoPdvEvento::TIPO_TRANSFERIDO => [
                'prioridad' => self::PRIORIDAD_ALTA,
                'tono_configurable' => false,
                'guion' => 'Turno {folio} transferido a {vendedor}',
            ],
            TurnoPdvEvento::TIPO_ESPERA_PROXIMO_VENCER => [
                'prioridad' => self::PRIORIDAD_CRITICA,
                'tono_configurable' => false,
                'guion' => 'Turno {folio} próximo a vencer',
            ],
            TurnoPdvEvento::TIPO_PRORROGA => [
                'prioridad' => self::PRIORIDAD_ALTA,
                'tono_configurable' => true,
                'guion' => 'Turno {folio} en prórroga',
            ],
            TurnoPdvEvento::TIPO_PRORROGA_PROXIMO_VENCER => [
                'prioridad' => self::PRIORIDAD_CRITICA,
                'tono_configurable' => false,
                'guion' => 'Prórroga del turno {folio} próxima a vencer',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function tiposTerminal(): array
    {
        return array_keys(self::definiciones());
    }

    public static function prioridadDe(string $tipo): string
    {
        return self::definiciones()[$tipo]['prioridad'] ?? self::PRIORIDAD_NORMAL;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function guionTerminal(string $tipo, array $datos): ?string
    {
        $definicion = self::definiciones()[$tipo] ?? null;
        if ($definicion === null) {
            return null;
        }

        $folio = trim((string) ($datos['folio'] ?? ''));
        if ($folio === '') {
            return null;
        }

        $vendedor = trim((string) ($datos['atencion']['primer_nombre'] ?? $datos['atencion_primer_nombre'] ?? ''));
        $clasificacion = self::clasificacionAlta($datos);

        if ($tipo === TurnoPdvEvento::TIPO_ALTA) {
            if ($clasificacion === 'diamante') {
                return "Nuevo turno Diamante, {$folio}";
            }
            if ($clasificacion === 'vip') {
                return "Nuevo turno VIP, {$folio}";
            }
        }

        $plantilla = $definicion['guion'];
        $mensaje = str_replace('{folio}', $folio, $plantilla);
        $mensaje = str_replace('{vendedor}', $vendedor !== '' ? $vendedor : 'el vendedor asignado', $mensaje);

        return $mensaje;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function clasificacionAlta(array $datos): ?string
    {
        if (($datos['prioridad_diamante'] ?? false) === true) {
            return 'diamante';
        }
        if (($datos['prioridad_vip'] ?? false) === true) {
            return 'vip';
        }

        return null;
    }
}
