<?php

namespace App\Services\ControlPedidos;

use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use Illuminate\Support\Facades\Cache;

class PlazosRetrasoPedidoBmaConfig
{
    public const CLAVE = 'control_pedidos.plazos_retraso';

    public const CACHE_KEY = 'control_pedidos.plazos_retraso';

    /**
     * @return array{
     *   activo: bool,
     *   hora_corte: string,
     *   dias_habiles: list<int>,
     *   temporada_alta: bool,
     *   dias_extra_temporada_alta: int,
     *   comercial: array{dias_empaque: int, dias_recoleccion: int},
     *   local_regional: array{dias_empaque: int, dias_recoleccion: int}
     * }
     */
    public function configuracionPorDefecto(): array
    {
        return [
            'activo' => true,
            'hora_corte' => '18:00',
            'dias_habiles' => [1, 2, 3, 4, 5, 6],
            'temporada_alta' => false,
            'dias_extra_temporada_alta' => 1,
            'comercial' => [
                'dias_empaque' => 1,
                'dias_recoleccion' => 1,
            ],
            'local_regional' => [
                'dias_empaque' => 1,
                'dias_recoleccion' => 1,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $configuracion
     * @return array{
     *   activo: bool,
     *   hora_corte: string,
     *   dias_habiles: list<int>,
     *   temporada_alta: bool,
     *   dias_extra_temporada_alta: int,
     *   comercial: array{dias_empaque: int, dias_recoleccion: int},
     *   local_regional: array{dias_empaque: int, dias_recoleccion: int}
     * }
     */
    public function normalizar(array $configuracion): array
    {
        $defaults = $this->configuracionPorDefecto();

        $diasHabiles = $configuracion['dias_habiles'] ?? $defaults['dias_habiles'];
        if (! is_array($diasHabiles)) {
            $diasHabiles = $defaults['dias_habiles'];
        }

        $diasHabiles = array_values(array_unique(array_filter(array_map(
            static fn ($dia) => (int) $dia,
            $diasHabiles
        ), static fn (int $dia) => $dia >= 1 && $dia <= 7)));

        if ($diasHabiles === []) {
            $diasHabiles = $defaults['dias_habiles'];
        }

        sort($diasHabiles);

        $horaCorte = (string) ($configuracion['hora_corte'] ?? $defaults['hora_corte']);
        if (! preg_match('/^\d{1,2}:\d{2}$/', $horaCorte)) {
            $horaCorte = $defaults['hora_corte'];
        } else {
            [$h, $m] = array_map('intval', explode(':', $horaCorte));
            if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
                $horaCorte = $defaults['hora_corte'];
            } else {
                $horaCorte = sprintf('%02d:%02d', $h, $m);
            }
        }

        return [
            'activo' => (bool) ($configuracion['activo'] ?? $defaults['activo']),
            'hora_corte' => $horaCorte,
            'dias_habiles' => $diasHabiles,
            'temporada_alta' => (bool) ($configuracion['temporada_alta'] ?? $defaults['temporada_alta']),
            'dias_extra_temporada_alta' => max(0, (int) ($configuracion['dias_extra_temporada_alta'] ?? $defaults['dias_extra_temporada_alta'])),
            'comercial' => $this->normalizarBloqueCategoria(
                is_array($configuracion['comercial'] ?? null) ? $configuracion['comercial'] : [],
                $defaults['comercial']
            ),
            'local_regional' => $this->normalizarBloqueCategoria(
                is_array($configuracion['local_regional'] ?? null) ? $configuracion['local_regional'] : [],
                $defaults['local_regional']
            ),
        ];
    }

    /**
     * @return array{
     *   activo: bool,
     *   hora_corte: string,
     *   dias_habiles: list<int>,
     *   temporada_alta: bool,
     *   dias_extra_temporada_alta: int,
     *   comercial: array{dias_empaque: int, dias_recoleccion: int},
     *   local_regional: array{dias_empaque: int, dias_recoleccion: int}
     * }
     */
    public function obtener(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $row = ConfiguracionSistema::query()->where('clave', self::CLAVE)->first();
            $raw = $row?->valor;
            $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);

            return $this->normalizar(is_array($decoded) ? $decoded : []);
        });
    }

    /**
     * @param  array<string, mixed>  $configuracion
     * @return array{
     *   activo: bool,
     *   hora_corte: string,
     *   dias_habiles: list<int>,
     *   temporada_alta: bool,
     *   dias_extra_temporada_alta: int,
     *   comercial: array{dias_empaque: int, dias_recoleccion: int},
     *   local_regional: array{dias_empaque: int, dias_recoleccion: int}
     * }
     */
    public function guardar(array $configuracion): array
    {
        $normalizada = $this->normalizar($configuracion);

        ConfiguracionSistema::updateOrCreate(
            ['clave' => self::CLAVE],
            [
                'valor' => json_encode($normalizada, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Plazos de retraso de empaque y recolección (Control Pedidos)',
            ]
        );

        Cache::forget(self::CACHE_KEY);

        return $normalizada;
    }

    /**
     * @param  array{
     *   activo: bool,
     *   hora_corte: string,
     *   dias_habiles: list<int>,
     *   temporada_alta: bool,
     *   dias_extra_temporada_alta: int,
     *   comercial: array{dias_empaque: int, dias_recoleccion: int},
     *   local_regional: array{dias_empaque: int, dias_recoleccion: int}
     * }  $config
     * @return array{dias_empaque: int, dias_recoleccion: int}
     */
    public function plazosParaCategoria(array $config, ?string $categoria): array
    {
        $bloque = ($categoria === CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL)
            ? $config['local_regional']
            : $config['comercial'];

        $extra = ! empty($config['temporada_alta'])
            ? (int) $config['dias_extra_temporada_alta']
            : 0;

        return [
            'dias_empaque' => max(1, (int) $bloque['dias_empaque'] + $extra),
            'dias_recoleccion' => max(1, (int) $bloque['dias_recoleccion'] + $extra),
        ];
    }

    /**
     * @param  array<string, mixed>  $bloque
     * @param  array{dias_empaque: int, dias_recoleccion: int}  $defaults
     * @return array{dias_empaque: int, dias_recoleccion: int}
     */
    private function normalizarBloqueCategoria(array $bloque, array $defaults): array
    {
        return [
            'dias_empaque' => max(1, (int) ($bloque['dias_empaque'] ?? $defaults['dias_empaque'])),
            'dias_recoleccion' => max(1, (int) ($bloque['dias_recoleccion'] ?? $defaults['dias_recoleccion'])),
        ];
    }
}
