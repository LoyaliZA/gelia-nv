<?php

namespace App\Support;

use Carbon\Carbon;

final class PresenciaCatalogo
{
    public const EN_LINEA_SEGUNDOS = 90;

    /**
     * En línea si hay una pestaña vigente. Si el arreglo de pestañas existe y está
     * vacío, la última sesión avisó que se ocultó. Sin esa clave se usa la actividad.
     */
    public static function enLinea(array $prefs, ?Carbon $ahora = null): bool
    {
        $ahora = $ahora ?? now();
        $pestanas = $prefs['pestanas'] ?? null;

        if (is_array($pestanas)) {
            foreach ($pestanas as $expira) {
                if (! is_string($expira) || $expira === '') {
                    continue;
                }
                try {
                    if (Carbon::parse($expira)->greaterThan($ahora)) {
                        return true;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            return false;
        }

        $ultima = $prefs['ultima_actividad_at'] ?? null;
        if (! is_string($ultima) || $ultima === '') {
            return false;
        }

        try {
            return Carbon::parse($ultima)->greaterThan($ahora->copy()->subSeconds(self::EN_LINEA_SEGUNDOS));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function estados(): array
    {
        $config = config('presencia.estados', []);

        if (is_array($config) && $config !== []) {
            return $config;
        }

        return self::estadosPorDefecto();
    }

    public static function slugs(): array
    {
        return array_keys(self::estados());
    }

    public static function esSlugValido(string $slug): bool
    {
        return in_array($slug, self::slugs(), true);
    }

    public static function meta(string $slug): ?array
    {
        return self::estados()[$slug] ?? null;
    }

    public static function defaults(): array
    {
        $config = config('presencia.defaults', []);

        if (is_array($config) && $config !== []) {
            return $config;
        }

        return [
            'estado' => 'disponible',
            'modo' => 'automatico',
            'automatizar' => true,
            'mensaje' => null,
            'expira_at' => null,
            'ultima_actividad_at' => null,
            'inactividad_minutos' => 45,
            'inactividad_estado' => 'ausente',
            'horarios' => [
                [
                    'estado' => 'comiendo',
                    'dias' => [1, 2, 3, 4, 5],
                    'inicio' => '13:00',
                    'fin' => '14:00',
                ],
                [
                    'estado' => 'en_junta',
                    'dias' => [1, 2, 3, 4, 5],
                    'inicio' => '09:00',
                    'fin' => '10:00',
                ],
            ],
        ];
    }

    private static function estadosPorDefecto(): array
    {
        return [
            'disponible' => [
                'etiqueta' => 'Disponible',
                'emoji' => '🟢',
                'color' => '#22c55e',
            ],
            'en_junta' => [
                'etiqueta' => 'En junta',
                'emoji' => '📅',
                'color' => '#8b5cf6',
            ],
            'comiendo' => [
                'etiqueta' => 'Comiendo',
                'emoji' => '🍽️',
                'color' => '#f59e0b',
            ],
            'en_ruta_venta' => [
                'etiqueta' => 'En ruta de venta',
                'emoji' => '🚗',
                'color' => '#3b82f6',
            ],
            'ocupado' => [
                'etiqueta' => 'Ocupado',
                'emoji' => '🔴',
                'color' => '#ef4444',
            ],
            'ausente' => [
                'etiqueta' => 'Ausente',
                'emoji' => '⏸️',
                'color' => '#94a3b8',
            ],
        ];
    }
}
