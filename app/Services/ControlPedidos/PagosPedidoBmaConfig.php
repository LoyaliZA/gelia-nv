<?php

namespace App\Services\ControlPedidos;

use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Fuente tipada de reglas monetarias de pagos BMA.
 * No usar literales de tolerancia en servicios/controladores/React.
 */
class PagosPedidoBmaConfig
{
    public const CLAVE_TOLERANCIA = 'control_pedidos.pagos.tolerancia_mxn';

    public const CLAVE_UI_SIMPLIFICADA = 'control_pedidos.auxiliar.ui_simplificada';

    public const DEFAULT_TOLERANCIA = '0.44';

    public const CACHE_KEY = 'control_pedidos.pagos.config';

    /**
     * Tolerancia en centavos (entero) para comparar sin float.
     */
    public function toleranciaCentavos(): int
    {
        return self::aCentavos($this->toleranciaMxn());
    }

    /**
     * Tolerancia normalizada como string decimal (2 decimales).
     */
    public function toleranciaMxn(): string
    {
        $raw = $this->valor(self::CLAVE_TOLERANCIA, self::DEFAULT_TOLERANCIA);

        return $this->normalizarDecimal((string) $raw);
    }

    public function uiSimplificada(): bool
    {
        $raw = $this->valor(self::CLAVE_UI_SIMPLIFICADA, '0');

        if (is_bool($raw)) {
            return $raw;
        }

        $v = strtolower(trim((string) $raw));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array{tolerancia_mxn: string, ui_simplificada: bool}
     */
    public function todas(): array
    {
        return [
            'tolerancia_mxn' => $this->toleranciaMxn(),
            'ui_simplificada' => $this->uiSimplificada(),
        ];
    }

    public function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('configuraciones_sistema_globales');
    }

    public static function aCentavos(string|float|int $monto): int
    {
        $normalizado = is_string($monto)
            ? $monto
            : number_format((float) $monto, 2, '.', '');

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $normalizado)) {
            throw new InvalidArgumentException('Importe monetario inválido.');
        }

        return (int) round((float) $normalizado * 100);
    }

    public static function centavosADecimal(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    private function normalizarDecimal(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '' || ! is_numeric($valor)) {
            throw new InvalidArgumentException(
                'Configuración '.self::CLAVE_TOLERANCIA.' inválida: se esperaba un decimal no negativo.'
            );
        }

        $float = (float) $valor;
        if ($float < 0) {
            throw new InvalidArgumentException(
                'Configuración '.self::CLAVE_TOLERANCIA.' inválida: no admite negativos.'
            );
        }

        // Rechazar más de 2 decimales significativos.
        if (preg_match('/\.\d{3,}$/', $valor)) {
            throw new InvalidArgumentException(
                'Configuración '.self::CLAVE_TOLERANCIA.' inválida: máximo 2 decimales.'
            );
        }

        return number_format($float, 2, '.', '');
    }

    private function valor(string $clave, mixed $default): mixed
    {
        $mapa = Cache::remember(self::CACHE_KEY, 60, function () {
            return ConfiguracionSistema::query()
                ->where('grupo', 'ControlPedidos')
                ->whereIn('clave', [self::CLAVE_TOLERANCIA, self::CLAVE_UI_SIMPLIFICADA])
                ->pluck('valor', 'clave')
                ->all();
        });

        if (! array_key_exists($clave, $mapa) || $mapa[$clave] === null || $mapa[$clave] === '') {
            return $default;
        }

        return $mapa[$clave];
    }
}
