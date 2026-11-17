<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\ConfiguracionSistema;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

final class HorarioCierreOperacionPdvConfig
{
    public const CLAVE = 'pdv.operacion.horario_cierre';

    public const CACHE_KEY = 'pdv.operacion.horario_cierre';

    /** @var list<int> */
    public const DIAS_OPERACION_PREDETERMINADOS = [1, 2, 3, 4, 5, 6];

    /**
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   hora_apertura: string|null,
     *   hora_cierre: string,
     *   dias_operacion: list<int>,
     *   hora_apertura_sabado: string|null,
     *   hora_cierre_sabado: string|null,
     *   por_sucursal: array<string, array<string, mixed>>
     * }
     */
    public function configuracionInicialPlaneada(): array
    {
        return [
            'activo' => true,
            'zona_horaria' => (string) config('app.timezone', 'America/Mexico_City'),
            'hora_apertura' => null,
            'hora_cierre' => '19:00',
            'dias_operacion' => self::DIAS_OPERACION_PREDETERMINADOS,
            'hora_apertura_sabado' => null,
            'hora_cierre_sabado' => null,
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
                'descripcion' => 'Horario operativo PDV (apertura y cierre)',
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
     *   hora_apertura: string|null,
     *   hora_cierre: string,
     *   dias_operacion: list<int>,
     *   hora_apertura_sabado: string|null,
     *   hora_cierre_sabado: string|null,
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
     * Configuración combinada (global + override), sin filtrar por día.
     *
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   hora_apertura: string|null,
     *   hora_cierre: string,
     *   dias_operacion: list<int>,
     *   hora_apertura_sabado: string|null,
     *   hora_cierre_sabado: string|null,
     *   por_sucursal: array<string, array<string, mixed>>
     * }|null
     */
    public function combinadoParaSucursal(?int $sucursalId): ?array
    {
        $global = $this->obtenerGlobal();
        if ($global === null || ! $global['activo']) {
            return null;
        }

        if ($sucursalId === null) {
            return $global;
        }

        $overrides = $global['por_sucursal'][(string) $sucursalId] ?? null;
        if (! is_array($overrides) || $overrides === []) {
            return $global;
        }

        $combinado = $this->normalizarCompleto(array_merge($global, $overrides));

        if (! $combinado['activo']) {
            return null;
        }

        return $combinado;
    }

    /**
     * @return array{
     *   zona_horaria: string,
     *   hora_apertura: string|null,
     *   hora_cierre: string
     * }|null
     */
    public function resolverParaSucursal(?int $sucursalId, ?CarbonInterface $cuando = null): ?array
    {
        $combinado = $this->combinadoParaSucursal($sucursalId);
        if ($combinado === null) {
            return null;
        }

        $cuando = $cuando ?? now();
        if (! $this->operaEn($combinado, $cuando)) {
            return null;
        }

        return $this->horarioEfectivo($combinado, $cuando);
    }

    public function operaEnFecha(?int $sucursalId, CarbonInterface $cuando): bool
    {
        $combinado = $this->combinadoParaSucursal($sucursalId);
        if ($combinado === null) {
            return false;
        }

        return $this->operaEn($combinado, $cuando);
    }

    /**
     * @return array{
     *   configurado: bool,
     *   hora_apertura: string|null,
     *   hora_cierre: string|null,
     *   zona_horaria: string|null,
     *   es_override_sucursal: bool,
     *   dias_operacion: list<int>,
     *   hora_apertura_semana: string|null,
     *   hora_cierre_semana: string|null,
     *   hora_apertura_sabado: string|null,
     *   hora_cierre_sabado: string|null,
     *   opera_hoy: bool
     * }
     */
    public function serializarParaSucursal(?int $sucursalId, ?CarbonInterface $cuando = null): array
    {
        $cuando = $cuando ?? now();
        $global = $this->obtenerGlobal();
        $combinado = $this->combinadoParaSucursal($sucursalId);
        $efectivo = $this->resolverParaSucursal($sucursalId, $cuando);

        return [
            'configurado' => $combinado !== null,
            'hora_apertura' => $efectivo['hora_apertura'] ?? null,
            'hora_cierre' => $efectivo['hora_cierre'] ?? null,
            'zona_horaria' => $combinado['zona_horaria'] ?? ($efectivo['zona_horaria'] ?? null),
            'es_override_sucursal' => $sucursalId !== null && isset($global['por_sucursal'][(string) $sucursalId]),
            'dias_operacion' => $combinado['dias_operacion'] ?? self::DIAS_OPERACION_PREDETERMINADOS,
            'hora_apertura_semana' => $combinado['hora_apertura'] ?? null,
            'hora_cierre_semana' => $combinado['hora_cierre'] ?? null,
            'hora_apertura_sabado' => $combinado['hora_apertura_sabado'] ?? null,
            'hora_cierre_sabado' => $combinado['hora_cierre_sabado'] ?? null,
            'opera_hoy' => $combinado !== null && $this->operaEnFecha($sucursalId, $cuando),
        ];
    }

    public function estaAntesDeApertura(int $sucursalId, CarbonInterface $ahora): bool
    {
        $horario = $this->resolverParaSucursal($sucursalId, $ahora);
        if ($horario === null || $horario['hora_apertura'] === null) {
            return false;
        }

        $zona = $horario['zona_horaria'];
        $ahoraLocal = $ahora->copy()->timezone($zona);
        $fechaOperativa = $ahoraLocal->toDateString();
        [$hora, $minuto] = array_map('intval', explode(':', $horario['hora_apertura']));
        $umbral = Carbon::parse($fechaOperativa, $zona)->setTime($hora, $minuto, 0);

        return $ahoraLocal->lt($umbral);
    }

    /**
     * @param  array<string, mixed>  $configuracion
     * @return array{
     *   activo: bool,
     *   zona_horaria: string,
     *   hora_apertura: string|null,
     *   hora_cierre: string,
     *   dias_operacion: list<int>,
     *   hora_apertura_sabado: string|null,
     *   hora_cierre_sabado: string|null,
     *   por_sucursal: array<string, array<string, mixed>>
     * }
     */
    public function normalizarCompleto(array $configuracion): array
    {
        $zona = trim((string) ($configuracion['zona_horaria'] ?? ''));
        if ($zona === '') {
            $zona = (string) config('app.timezone', 'America/Mexico_City');
        }

        $horaApertura = $this->normalizarHora($configuracion['hora_apertura'] ?? null);
        $horaCierre = $this->normalizarHora($configuracion['hora_cierre'] ?? null);

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
            'hora_apertura' => $horaApertura,
            'hora_cierre' => $horaCierre ?? '',
            'dias_operacion' => $this->normalizarDias($configuracion['dias_operacion'] ?? null),
            'hora_apertura_sabado' => $this->normalizarHora($configuracion['hora_apertura_sabado'] ?? null),
            'hora_cierre_sabado' => $this->normalizarHora($configuracion['hora_cierre_sabado'] ?? null),
            'por_sucursal' => $porSucursalNormalizado,
        ];
    }

    /**
     * @param  array{
     *   activo: bool,
     *   zona_horaria: string,
     *   hora_apertura?: string|null,
     *   hora_cierre: string,
     *   dias_operacion?: list<int>,
     *   hora_apertura_sabado?: string|null,
     *   hora_cierre_sabado?: string|null,
     *   por_sucursal?: array<string, array<string, mixed>>
     * }  $config
     */
    private function operaEn(array $config, CarbonInterface $cuando): bool
    {
        $zona = (string) $config['zona_horaria'];
        $dia = (int) $cuando->copy()->timezone($zona)->isoWeekday();
        $dias = $config['dias_operacion'] ?? self::DIAS_OPERACION_PREDETERMINADOS;

        return in_array($dia, $dias, true);
    }

    private function normalizarHora(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $valor, $coincidencias) !== 1) {
            return null;
        }

        return $coincidencias[1].':'.$coincidencias[2];
    }

    /**
     * @return list<int>
     */
    private function normalizarDias(mixed $valor): array
    {
        if (! is_array($valor) || $valor === []) {
            return self::DIAS_OPERACION_PREDETERMINADOS;
        }

        $dias = [];
        foreach ($valor as $dia) {
            if (! is_numeric($dia)) {
                continue;
            }
            $entero = (int) $dia;
            if ($entero < 1 || $entero > 7) {
                continue;
            }
            $dias[$entero] = $entero;
        }

        if ($dias === []) {
            return self::DIAS_OPERACION_PREDETERMINADOS;
        }

        $ordenados = array_values($dias);
        sort($ordenados);

        return $ordenados;
    }

    /**
     * @param  array{
     *   activo: bool,
     *   zona_horaria: string,
     *   hora_apertura?: string|null,
     *   hora_cierre: string,
     *   hora_apertura_sabado?: string|null,
     *   hora_cierre_sabado?: string|null
     * }  $config
     * @return array{zona_horaria: string, hora_apertura: string|null, hora_cierre: string}|null
     */
    private function horarioEfectivo(array $config, CarbonInterface $cuando): ?array
    {
        $horaCierre = $this->normalizarHora($config['hora_cierre'] ?? null);
        $horaApertura = $this->normalizarHora($config['hora_apertura'] ?? null);

        $local = $cuando->copy()->timezone((string) $config['zona_horaria']);
        if ((int) $local->isoWeekday() === 6) {
            $aperturaSabado = $this->normalizarHora($config['hora_apertura_sabado'] ?? null);
            $cierreSabado = $this->normalizarHora($config['hora_cierre_sabado'] ?? null);
            if ($aperturaSabado !== null) {
                $horaApertura = $aperturaSabado;
            }
            if ($cierreSabado !== null) {
                $horaCierre = $cierreSabado;
            }
        }

        if ($horaCierre === null) {
            return null;
        }

        return [
            'zona_horaria' => (string) $config['zona_horaria'],
            'hora_apertura' => $horaApertura,
            'hora_cierre' => $horaCierre,
        ];
    }
}
