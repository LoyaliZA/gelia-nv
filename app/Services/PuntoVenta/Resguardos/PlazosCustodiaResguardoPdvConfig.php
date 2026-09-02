<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelvePlazosCustodiaResguardoPdv;
use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Cache;

class PlazosCustodiaResguardoPdvConfig implements ResuelvePlazosCustodiaResguardoPdv
{
    public const CLAVE = 'pdv.resguardos.plazos_custodia';

    public const CACHE_KEY = 'pdv.resguardos.plazos_custodia';

    public const TIPO_DIAS_NATURALES = 'naturales';

    public const TIPO_DIAS_HABILES = 'habiles';

    /**
     * Valores aprobados en CONTRATO_RESGUARDOS_PDV §5.5 / §9 — solo para migración/seeder.
     *
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   tipo_dias: string,
     *   dias_habiles: list<int>,
     *   custodia_dias: int,
     *   aviso_previo_dias: int,
     *   rezago_dias: int,
     *   por_sucursal: array<string, array<string, mixed>>
     * }
     */
    public function configuracionInicialAprobada(): array
    {
        return [
            'activo' => true,
            'zona_horaria' => (string) config('app.timezone'),
            'tipo_dias' => self::TIPO_DIAS_HABILES,
            'dias_habiles' => [1, 2, 3, 4, 5],
            'custodia_dias' => 15,
            'aviso_previo_dias' => 3,
            'rezago_dias' => 15,
            'por_sucursal' => [],
        ];
    }

    public function estaConfigurado(): bool
    {
        return ConfiguracionSistema::query()->where('clave', self::CLAVE)->exists();
    }

    /**
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   tipo_dias: string,
     *   dias_habiles: list<int>,
     *   custodia_dias: int,
     *   aviso_previo_dias: int,
     *   rezago_dias: int,
     *   por_sucursal: array<string, array<string, mixed>>
     * }|null
     */
    public function obtenerGlobal(): ?array
    {
        if (! $this->estaConfigurado()) {
            return null;
        }

        return Cache::rememberForever(self::CACHE_KEY, function () {
            $row = ConfiguracionSistema::query()->where('clave', self::CLAVE)->first();
            $raw = $row?->valor;
            $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);

            return $this->normalizarCompleto(is_array($decoded) ? $decoded : []);
        });
    }

    /**
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   tipo_dias: string,
     *   dias_habiles: list<int>,
     *   custodia_dias: int,
     *   aviso_previo_dias: int,
     *   rezago_dias: int
     * }|null
     */
    public function resolverParaSucursal(?int $sucursalId): ?array
    {
        $global = $this->obtenerGlobal();
        if ($global === null || ! $global['activo']) {
            return null;
        }

        $efectivo = $this->plazosEfectivos($global);

        if ($sucursalId === null) {
            return $efectivo;
        }

        $overrides = $global['por_sucursal'][(string) $sucursalId] ?? null;
        if (! is_array($overrides) || $overrides === []) {
            return $efectivo;
        }

        return $this->normalizar(array_merge($global, $overrides));
    }

    /**
     * @param  array<string, mixed>  $configuracion
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   tipo_dias: string,
     *   dias_habiles: list<int>,
     *   custodia_dias: int,
     *   aviso_previo_dias: int,
     *   rezago_dias: int
     * }
     */
    public function normalizar(array $configuracion): array
    {
        return $this->plazosEfectivos($this->normalizarCompleto($configuracion));
    }

    /**
     * @param  array<string, mixed>  $configuracion
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   tipo_dias: string,
     *   dias_habiles: list<int>,
     *   custodia_dias: int,
     *   aviso_previo_dias: int,
     *   rezago_dias: int,
     *   por_sucursal: array<string, array<string, mixed>>
     * }
     */
    public function normalizarCompleto(array $configuracion): array
    {
        $base = $this->configuracionInicialAprobada();

        $diasHabiles = $configuracion['dias_habiles'] ?? $base['dias_habiles'];
        if (! is_array($diasHabiles)) {
            $diasHabiles = $base['dias_habiles'];
        }

        $diasHabiles = array_values(array_unique(array_filter(array_map(
            static fn ($dia) => (int) $dia,
            $diasHabiles
        ), static fn (int $dia) => $dia >= 1 && $dia <= 7)));

        if ($diasHabiles === []) {
            $diasHabiles = $base['dias_habiles'];
        }

        sort($diasHabiles);

        $tipoDias = strtolower(trim((string) ($configuracion['tipo_dias'] ?? $base['tipo_dias'])));
        if (! in_array($tipoDias, [self::TIPO_DIAS_NATURALES, self::TIPO_DIAS_HABILES], true)) {
            $tipoDias = $base['tipo_dias'];
        }

        $zona = trim((string) ($configuracion['zona_horaria'] ?? $base['zona_horaria']));
        if ($zona === '') {
            $zona = $base['zona_horaria'];
        }

        $porSucursal = $configuracion['por_sucursal'] ?? $base['por_sucursal'];
        if (! is_array($porSucursal)) {
            $porSucursal = [];
        }

        $porSucursalNormalizado = [];
        foreach ($porSucursal as $sucursalId => $override) {
            if (! is_numeric($sucursalId) || ! is_array($override)) {
                continue;
            }
            $porSucursalNormalizado[(string) (int) $sucursalId] = $override;
        }

        return [
            'activo' => (bool) ($configuracion['activo'] ?? $base['activo']),
            'zona_horaria' => $zona,
            'tipo_dias' => $tipoDias,
            'dias_habiles' => $diasHabiles,
            'custodia_dias' => max(1, (int) ($configuracion['custodia_dias'] ?? $base['custodia_dias'])),
            'aviso_previo_dias' => max(1, (int) ($configuracion['aviso_previo_dias'] ?? $base['aviso_previo_dias'])),
            'rezago_dias' => max(1, (int) ($configuracion['rezago_dias'] ?? $base['rezago_dias'])),
            'por_sucursal' => $porSucursalNormalizado,
        ];
    }

    /**
     * @param  array<string, mixed>  $configuracion
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   tipo_dias: string,
     *   dias_habiles: list<int>,
     *   custodia_dias: int,
     *   aviso_previo_dias: int,
     *   rezago_dias: int,
     *   por_sucursal: array<string, array<string, mixed>>
     * }
     */
    public function guardar(array $configuracion): array
    {
        $normalizada = $this->normalizarCompleto($configuracion);

        ConfiguracionSistema::updateOrCreate(
            ['clave' => self::CLAVE],
            [
                'valor' => json_encode($normalizada, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Plazos de custodia, aviso previo y rezago de resguardos PDV',
            ]
        );

        Cache::forget(self::CACHE_KEY);

        return $normalizada;
    }

    /**
     * @param  array{
     *   activo: bool,
     *   zona_horaria: string,
     *   tipo_dias: string,
     *   dias_habiles: list<int>,
     *   custodia_dias: int,
     *   aviso_previo_dias: int,
     *   rezago_dias: int,
     *   por_sucursal?: array<string, array<string, mixed>>
     * }  $config
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   tipo_dias: string,
     *   dias_habiles: list<int>,
     *   custodia_dias: int,
     *   aviso_previo_dias: int,
     *   rezago_dias: int
     * }
     */
    private function plazosEfectivos(array $config): array
    {
        return [
            'activo' => (bool) $config['activo'],
            'zona_horaria' => (string) $config['zona_horaria'],
            'tipo_dias' => (string) $config['tipo_dias'],
            'dias_habiles' => $config['dias_habiles'],
            'custodia_dias' => (int) $config['custodia_dias'],
            'aviso_previo_dias' => (int) $config['aviso_previo_dias'],
            'rezago_dias' => (int) $config['rezago_dias'],
        ];
    }
}
