<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Cache;

final class HorarioCierreOperacionPdvConfig
{
    public const CLAVE = 'pdv.operacion.horario_cierre';

    public const CACHE_KEY = 'pdv.operacion.horario_cierre';

    /**
     * Valor provisional aprobado en planeación (5D); editable desde UI operación.
     *
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   hora_cierre: string,
     *   por_sucursal: array<string, array<string, mixed>>
     * }
     */
    public function configuracionInicialPlaneada(): array
    {
        return [
            'activo' => true,
            'zona_horaria' => (string) config('app.timezone', 'America/Mexico_City'),
            'hora_cierre' => '19:00',
            'por_sucursal' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $configuracion
     */
    public function persistir(array $configuracion): void
    {
        $normalizado = $this->normalizarCompleto($configuracion);

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => self::CLAVE],
            [
                'valor' => json_encode($normalizado, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Horario de cierre operativo PDV',
            ]
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function estaConfigurado(): bool
    {
        return ConfiguracionSistema::query()->where('clave', self::CLAVE)->exists();
    }

    /**
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   hora_cierre: string,
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
     *   zona_horaria: string,
     *   hora_cierre: string
     * }|null
     */
    public function resolverParaSucursal(?int $sucursalId): ?array
    {
        $global = $this->obtenerGlobal();
        if ($global === null || ! $global['activo']) {
            return null;
        }

        $efectivo = $this->horarioEfectivo($global);

        if ($sucursalId === null) {
            return $efectivo;
        }

        $overrides = $global['por_sucursal'][(string) $sucursalId] ?? null;
        if (! is_array($overrides) || $overrides === []) {
            return $efectivo;
        }

        $combinado = $this->normalizarCompleto(array_merge($global, $overrides));

        if (! $combinado['activo']) {
            return null;
        }

        return $this->horarioEfectivo($combinado);
    }

    /**
     * @param  array<string, mixed>  $configuracion
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   hora_cierre: string,
     *   por_sucursal: array<string, array<string, mixed>>
     * }
     */
    public function normalizarCompleto(array $configuracion): array
    {
        $zona = trim((string) ($configuracion['zona_horaria'] ?? ''));
        if ($zona === '') {
            $zona = (string) config('app.timezone', 'America/Mexico_City');
        }

        $horaCierre = $this->normalizarHoraCierre($configuracion['hora_cierre'] ?? null);

        $porSucursal = $configuracion['por_sucursal'] ?? [];
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
            'activo' => (bool) ($configuracion['activo'] ?? false),
            'zona_horaria' => $zona,
            'hora_cierre' => $horaCierre ?? '',
            'por_sucursal' => $porSucursalNormalizado,
        ];
    }

    private function normalizarHoraCierre(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $valor, $coincidencias) !== 1) {
            return null;
        }

        return $coincidencias[1].':'.$coincidencias[2];
    }

    /**
     * @param  array{
     *   activo: bool,
     *   zona_horaria: string,
     *   hora_cierre: string,
     *   por_sucursal?: array<string, array<string, mixed>>
     * }  $config
     * @return array{zona_horaria: string, hora_cierre: string}|null
     */
    private function horarioEfectivo(array $config): ?array
    {
        $hora = $this->normalizarHoraCierre($config['hora_cierre'] ?? null);
        if ($hora === null) {
            return null;
        }

        return [
            'zona_horaria' => (string) $config['zona_horaria'],
            'hora_cierre' => $hora,
        ];
    }
}
