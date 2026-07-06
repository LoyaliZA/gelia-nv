<?php

namespace App\Support;

class MatrizHorarioTurno
{
  /** @var list<string> */
    public const DIAS = [
        'lunes',
        'martes',
        'miercoles',
        'jueves',
        'viernes',
        'sabado',
        'domingo',
    ];

    /** @var array<string, string> */
    private const MAPA_CLAVES_LEGACY = [
        '0' => 'domingo',
        '1' => 'lunes',
        '2' => 'martes',
        '3' => 'miercoles',
        '4' => 'jueves',
        '5' => 'viernes',
        '6' => 'sabado',
    ];

    /**
     * @return array<string, array{entrada: string, salida: string, horas: float|int, descanso: bool}>
     */
    public static function defecto(): array
    {
        return [
            'lunes' => ['entrada' => '09:00', 'salida' => '18:00', 'horas' => 9, 'descanso' => false],
            'martes' => ['entrada' => '09:00', 'salida' => '18:00', 'horas' => 9, 'descanso' => false],
            'miercoles' => ['entrada' => '09:00', 'salida' => '18:00', 'horas' => 9, 'descanso' => false],
            'jueves' => ['entrada' => '09:00', 'salida' => '18:00', 'horas' => 9, 'descanso' => false],
            'viernes' => ['entrada' => '09:00', 'salida' => '18:00', 'horas' => 9, 'descanso' => false],
            'sabado' => ['entrada' => '09:00', 'salida' => '14:00', 'horas' => 5, 'descanso' => false],
            'domingo' => ['entrada' => '00:00', 'salida' => '00:00', 'horas' => 0, 'descanso' => true],
        ];
    }

    /**
     * Convierte claves numéricas legacy ('0'–'6') a nombres en español y completa días faltantes.
     *
     * @param  array<string, mixed>|null  $matriz
     * @return array<string, array{entrada: string, salida: string, horas: float|int, descanso: bool}>
     */
    public static function normalizar(?array $matriz): array
    {
        $defecto = self::defecto();

        if (empty($matriz)) {
            return $defecto;
        }

        $normalizada = [];

        foreach ($matriz as $clave => $config) {
            if (!is_array($config)) {
                continue;
            }

            $dia = self::resolverClaveDia((string) $clave);

            if ($dia === null) {
                continue;
            }

            $normalizada[$dia] = self::normalizarDia($config, $defecto[$dia]);
        }

        foreach (self::DIAS as $dia) {
            if (!isset($normalizada[$dia])) {
                $normalizada[$dia] = $defecto[$dia];
            }
        }

        return $normalizada;
    }

    public static function resolverClaveDia(string $clave): ?string
    {
        $clave = strtolower(trim($clave));

        if (in_array($clave, self::DIAS, true)) {
            return $clave;
        }

        return self::MAPA_CLAVES_LEGACY[$clave] ?? null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array{entrada: string, salida: string, horas: float|int, descanso: bool}  $defectoDia
     * @return array{entrada: string, salida: string, horas: float|int, descanso: bool}
     */
    private static function normalizarDia(array $config, array $defectoDia): array
    {
        $descanso = (bool) ($config['descanso'] ?? $defectoDia['descanso']);
        $entrada = self::normalizarHora((string) ($config['entrada'] ?? $defectoDia['entrada']));
        $salida = self::normalizarHora((string) ($config['salida'] ?? $defectoDia['salida']));

        $horas = $config['horas'] ?? $defectoDia['horas'];
        if (!is_numeric($horas) && !$descanso && $entrada !== '00:00' && $salida !== '00:00') {
            $horas = self::calcularHoras($entrada, $salida);
        }

        return [
            'entrada' => $entrada,
            'salida' => $salida,
            'horas' => $descanso ? 0 : (float) $horas,
            'descanso' => $descanso,
        ];
    }

    private static function normalizarHora(string $hora): string
    {
        $hora = trim($hora);

        if ($hora === '') {
            return '00:00';
        }

        if (strlen($hora) >= 8) {
            return substr($hora, 0, 5);
        }

        return $hora;
    }

    private static function calcularHoras(string $entrada, string $salida): float
    {
        [$eh, $em] = array_map('intval', explode(':', $entrada));
        [$sh, $sm] = array_map('intval', explode(':', $salida));

        $minutos = max(0, ($sh * 60 + $sm) - ($eh * 60 + $em));

        return round($minutos / 60, 2);
    }

    /**
     * @return array{entrada: ?string, salida: ?string, horas: float, descanso: bool, dia: string, tiene_turno: bool}
     */
    public static function horarioParaFecha(?array $matriz, \Carbon\Carbon|string $fecha, float $fallbackHoras = 8): array
    {
        $fechaCarbon = $fecha instanceof \Carbon\Carbon ? $fecha : \Carbon\Carbon::parse($fecha);

        $mapaDias = [
            'Monday' => 'lunes',
            'Tuesday' => 'martes',
            'Wednesday' => 'miercoles',
            'Thursday' => 'jueves',
            'Friday' => 'viernes',
            'Saturday' => 'sabado',
            'Sunday' => 'domingo',
        ];

        $dia = $mapaDias[$fechaCarbon->format('l')] ?? 'lunes';
        $matrizNormalizada = self::normalizar($matriz);
        $configDia = $matrizNormalizada[$dia] ?? self::defecto()[$dia];

        return [
            'entrada' => $configDia['entrada'],
            'salida' => $configDia['salida'],
            'horas' => ($configDia['descanso'] ?? false) ? 0.0 : (float) $configDia['horas'],
            'descanso' => (bool) ($configDia['descanso'] ?? false),
            'dia' => $dia,
            'tiene_turno' => !empty($matriz),
        ];
    }
}
