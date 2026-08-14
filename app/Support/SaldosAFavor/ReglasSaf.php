<?php

namespace App\Support\SaldosAFavor;

use App\Models\ConfiguracionSistema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class ReglasSaf
{
    public const CLAVE_MONTO_MINIMO = 'saldos_favor.monto_minimo';

    public const CLAVE_VIGENCIA_MODO = 'saldos_favor.vigencia_modo';

    public const CLAVE_VIGENCIA_DIAS = 'saldos_favor.vigencia_dias';

    public const CLAVE_FECHA_LIMITE = 'saldos_favor.fecha_limite';

    public const MODO_DIAS = 'dias';

    public const MODO_FECHA_LIMITE = 'fecha_limite';

    public const DEFAULT_MONTO_MINIMO = 10.0;

    public const DEFAULT_VIGENCIA_DIAS = 20;

    public static function montoMinimo(): float
    {
        return round((float) self::valor(self::CLAVE_MONTO_MINIMO, self::DEFAULT_MONTO_MINIMO), 2);
    }

    public static function vigenciaModo(): string
    {
        $modo = (string) self::valor(self::CLAVE_VIGENCIA_MODO, self::MODO_DIAS);

        return in_array($modo, [self::MODO_DIAS, self::MODO_FECHA_LIMITE], true)
            ? $modo
            : self::MODO_DIAS;
    }

    public static function vigenciaDias(): int
    {
        return max(1, (int) self::valor(self::CLAVE_VIGENCIA_DIAS, self::DEFAULT_VIGENCIA_DIAS));
    }

    public static function fechaLimite(): ?Carbon
    {
        $raw = self::valor(self::CLAVE_FECHA_LIMITE, '');
        if ($raw === null || $raw === '') {
            return null;
        }

        return Carbon::parse((string) $raw)->startOfDay();
    }

    /**
     * @return array{
     *   monto_minimo: float,
     *   vigencia_modo: string,
     *   vigencia_dias: int,
     *   fecha_limite: string|null
     * }
     */
    public static function todas(): array
    {
        $limite = self::fechaLimite();

        return [
            'monto_minimo' => self::montoMinimo(),
            'vigencia_modo' => self::vigenciaModo(),
            'vigencia_dias' => self::vigenciaDias(),
            'fecha_limite' => $limite?->toDateString(),
        ];
    }

    public static function assertMontoMinimo(float $monto): void
    {
        $min = self::montoMinimo();
        if (round($monto, 2) + 0.001 < $min) {
            throw new InvalidArgumentException(
                'El monto mínimo para generar saldo a favor es '.number_format($min, 2, '.', '').'.'
            );
        }
    }

    public static function fechaVencimientoPara(?Carbon $fechaGeneracion = null, ?Carbon $fechaOverride = null): string
    {
        if ($fechaOverride !== null) {
            return $fechaOverride->toDateString();
        }

        $desde = ($fechaGeneracion ?? now())->copy()->startOfDay();

        if (self::vigenciaModo() === self::MODO_FECHA_LIMITE) {
            $limite = self::fechaLimite();
            if ($limite === null) {
                throw new InvalidArgumentException(
                    'La vigencia por fecha límite está activa pero no hay fecha configurada.'
                );
            }
            if ($limite->lt($desde)) {
                throw new InvalidArgumentException(
                    'La fecha límite de vigencia es anterior a la fecha de generación del saldo.'
                );
            }

            return $limite->toDateString();
        }

        return $desde->copy()->addDays(self::vigenciaDias())->toDateString();
    }

    /**
     * @param  array{
     *   monto_minimo: float|int|string,
     *   vigencia_modo: string,
     *   vigencia_dias: int|string,
     *   fecha_limite?: string|null
     * }  $datos
     */
    public static function guardar(array $datos): void
    {
        $modo = (string) ($datos['vigencia_modo'] ?? self::MODO_DIAS);
        if (! in_array($modo, [self::MODO_DIAS, self::MODO_FECHA_LIMITE], true)) {
            throw new InvalidArgumentException('Modo de vigencia inválido.');
        }

        $montoMinimo = round((float) ($datos['monto_minimo'] ?? self::DEFAULT_MONTO_MINIMO), 2);
        if ($montoMinimo < 0) {
            throw new InvalidArgumentException('El monto mínimo no puede ser negativo.');
        }

        $dias = max(1, (int) ($datos['vigencia_dias'] ?? self::DEFAULT_VIGENCIA_DIAS));
        $fechaLimite = trim((string) ($datos['fecha_limite'] ?? ''));

        if ($modo === self::MODO_FECHA_LIMITE && $fechaLimite === '') {
            throw new InvalidArgumentException('Debe indicar la fecha límite de vigencia.');
        }

        self::upsert(self::CLAVE_MONTO_MINIMO, (string) $montoMinimo, 'decimal', 'Monto mínimo para generar saldo a favor');
        self::upsert(self::CLAVE_VIGENCIA_MODO, $modo, 'string', 'Modo de vigencia: dias o fecha_limite');
        self::upsert(self::CLAVE_VIGENCIA_DIAS, (string) $dias, 'integer', 'Días de vigencia desde la generación');
        self::upsert(
            self::CLAVE_FECHA_LIMITE,
            $fechaLimite,
            'string',
            'Fecha límite de vigencia (modo fecha_limite)'
        );

        Cache::forget('configuraciones_sistema_globales');
        Cache::forget('saldos_favor.reglas');
    }

    private static function upsert(string $clave, string $valor, string $tipo, string $descripcion): void
    {
        ConfiguracionSistema::updateOrCreate(
            ['clave' => $clave],
            [
                'valor' => $valor,
                'tipo' => $tipo,
                'grupo' => 'saldos_favor',
                'descripcion' => $descripcion,
            ]
        );
    }

    private static function valor(string $clave, mixed $default): mixed
    {
        $mapa = Cache::remember('saldos_favor.reglas', 60, function () {
            return ConfiguracionSistema::query()
                ->where('grupo', 'saldos_favor')
                ->pluck('valor', 'clave')
                ->all();
        });

        if (! array_key_exists($clave, $mapa) || $mapa[$clave] === null || $mapa[$clave] === '') {
            return $default;
        }

        return $mapa[$clave];
    }
}
