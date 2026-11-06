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
            'inactividad_minutos' => 0,
            'inactividad_estado' => 'disponible',
            'horarios' => [],
        ];
    }

    private static function estadosPorDefecto(): array
    {
        return [
            'disponible' => [
                'etiqueta' => 'En línea',
                'emoji' => null,
                'color' => null,
            ],
        ];
    }
}
