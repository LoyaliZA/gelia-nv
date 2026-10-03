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
                'guion' => '{vendedor}, tienes un nuevo cliente: {cliente}.',
            ],
            TurnoPdvEvento::TIPO_REATENCION => [
                'prioridad' => self::PRIORIDAD_ALTA,
                'tono_configurable' => false,
                'guion' => '{vendedor}, tienes un nuevo cliente: {cliente}.',
            ],
            TurnoPdvEvento::TIPO_TRANSFERIDO => [
                'prioridad' => self::PRIORIDAD_ALTA,
                'tono_configurable' => false,
                'guion' => '{vendedor}, tienes un nuevo cliente: {cliente}.',
            ],
            TurnoPdvEvento::TIPO_ESPERA_PROXIMO_VENCER => [
                'prioridad' => self::PRIORIDAD_CRITICA,
                'tono_configurable' => false,
                'guion' => 'Turno {folio} próximo a vencer',
            ],
            TurnoPdvEvento::TIPO_PRORROGA => [
                'prioridad' => self::PRIORIDAD_ALTA,
                'tono_configurable' => true,
                'guion' => 'Prórroga iniciada. Turno {folio}.',
            ],
            TurnoPdvEvento::TIPO_PRORROGA_PROXIMO_VENCER => [
                'prioridad' => self::PRIORIDAD_CRITICA,
                'tono_configurable' => false,
                'guion' => 'Prórroga del turno {folio} próxima a vencer',
            ],
            'pausa.iniciada' => [
                'prioridad' => self::PRIORIDAD_ALTA,
                'tono_configurable' => false,
                'guion' => 'Pausa activa. {vendedor}.',
            ],
            'resguardo.recepcion_esperada_creada' => [
                'prioridad' => self::PRIORIDAD_ALTA,
                'tono_configurable' => false,
                'guion' => 'Nuevo resguardo pendiente de aprobación. {referencia}.',
            ],
            'resguardo.registro_manual_creado' => [
                'prioridad' => self::PRIORIDAD_ALTA,
                'tono_configurable' => false,
                'guion' => 'Nuevo resguardo pendiente de aprobación. {referencia}.',
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

        if ($tipo === 'pausa.iniciada') {
            $vendedor = self::primerNombre($datos);

            return $vendedor !== '' ? "Pausa activa. {$vendedor}." : 'Pausa activa.';
        }

        if (in_array($tipo, ['resguardo.recepcion_esperada_creada', 'resguardo.registro_manual_creado'], true)) {
            $referencia = trim((string) ($datos['folio'] ?? $datos['snapshot_cliente_nombre'] ?? ''));
            if ($referencia === '') {
                return null;
            }

            return "Nuevo resguardo pendiente de aprobación. {$referencia}.";
        }

        if (in_array($tipo, [
            TurnoPdvEvento::TIPO_ASIGNADO,
            TurnoPdvEvento::TIPO_REATENCION,
            TurnoPdvEvento::TIPO_TRANSFERIDO,
        ], true)) {
            return self::guionLlamado($datos, false);
        }

        if ($tipo === TurnoPdvEvento::TIPO_PRORROGA) {
            $folio = trim((string) ($datos['folio'] ?? ''));
            if ($folio === '') {
                return null;
            }

            return "Prórroga iniciada. Turno {$folio}.";
        }

        $folio = trim((string) ($datos['folio'] ?? ''));
        if ($folio === '') {
            return null;
        }

        $vendedor = self::primerNombre($datos);
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
    public static function guionLlamado(array $datos, bool $publico = false): ?string
    {
        $cliente = trim((string) ($datos['snapshot_nombre_llamado'] ?? ''));
        if ($cliente === '') {
            return null;
        }

        $vendedor = self::primerNombre($datos);

        if (! $publico) {
            return $vendedor !== ''
                ? "{$vendedor}, tienes un nuevo cliente: {$cliente}."
                : "Tienes un nuevo cliente: {$cliente}.";
        }

        $folio = trim((string) ($datos['folio'] ?? ''));
        if ($folio === '') {
            return null;
        }

        $partes = ["Turno {$folio}.", "{$cliente}."];

        if (($datos['prioridad_diamante'] ?? false) === true) {
            $partes[] = 'Tiene prioridad.';
        }

        $partes[] = 'Pase con '.($vendedor !== '' ? $vendedor : 'el vendedor asignado').'.';

        return implode(' ', $partes);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function primerNombre(array $datos): string
    {
        $atencion = is_array($datos['atencion'] ?? null) ? $datos['atencion'] : [];
        $jornada = is_array($datos['jornada'] ?? null) ? $datos['jornada'] : [];
        $candidatos = [
            $atencion['primer_nombre'] ?? null,
            $datos['atencion_primer_nombre'] ?? null,
            $datos['primer_nombre'] ?? null,
            $jornada['primer_nombre'] ?? null,
        ];

        foreach ($candidatos as $candidato) {
            $nombre = trim((string) $candidato);
            if ($nombre !== '') {
                return $nombre;
            }
        }

        return '';
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
