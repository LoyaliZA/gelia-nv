<?php

namespace App\Services\ControlPedidos;

use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Cache;

class FormularioProgresivoPedidoBmaConfig
{
    public const CLAVE_FLAG = 'control_pedidos.ventas.formulario_progresivo';

    public const CLAVE_AUTOSAVE_MS = 'control_pedidos.ventas.autosave_debounce_ms';

    public const CLAVE_MAX_REINTENTOS = 'control_pedidos.ventas.max_reintentos_autosave';

    public const CACHE_KEY = 'control_pedidos.ventas.formulario.config';

    public const DEFAULT_AUTOSAVE_MS = 15000;

    public const DEFAULT_MAX_REINTENTOS = 3;

    /**
     * @return array<string, array{valor: string, tipo: string, descripcion: string}>
     */
    public static function semillas(): array
    {
        return [
            self::CLAVE_FLAG => [
                'valor' => '0',
                'tipo' => 'boolean',
                'descripcion' => 'UI de formulario progresivo por etapas en Ventas (Control Pedidos)',
            ],
            self::CLAVE_AUTOSAVE_MS => [
                'valor' => (string) self::DEFAULT_AUTOSAVE_MS,
                'tipo' => 'integer',
                'descripcion' => 'Debounce en ms del autoguardado a BD del formulario de pedido',
            ],
            self::CLAVE_MAX_REINTENTOS => [
                'valor' => (string) self::DEFAULT_MAX_REINTENTOS,
                'tipo' => 'integer',
                'descripcion' => 'Reintentos máximos de autoguardado ante error de red',
            ],
        ];
    }

    public function formularioProgresivo(): bool
    {
        return $this->bool(self::CLAVE_FLAG, false);
    }

    public function autosaveDebounceMs(): int
    {
        $n = (int) $this->valor(self::CLAVE_AUTOSAVE_MS, self::DEFAULT_AUTOSAVE_MS);

        return max(1000, min(120000, $n));
    }

    public function maxReintentosAutosave(): int
    {
        $n = (int) $this->valor(self::CLAVE_MAX_REINTENTOS, self::DEFAULT_MAX_REINTENTOS);

        return max(0, min(10, $n));
    }

    /**
     * @return array{formulario_progresivo: bool, autosave_debounce_ms: int, max_reintentos_autosave: int}
     */
    public function todas(): array
    {
        return [
            'formulario_progresivo' => $this->formularioProgresivo(),
            'autosave_debounce_ms' => $this->autosaveDebounceMs(),
            'max_reintentos_autosave' => $this->maxReintentosAutosave(),
        ];
    }

    public function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('configuraciones_sistema_globales');
    }

    private function bool(string $clave, bool $default): bool
    {
        $raw = $this->valor($clave, $default ? '1' : '0');
        if (is_bool($raw)) {
            return $raw;
        }
        $v = strtolower(trim((string) $raw));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    private function valor(string $clave, mixed $default): mixed
    {
        $mapa = Cache::remember(self::CACHE_KEY, 60, function () {
            return ConfiguracionSistema::query()
                ->whereIn('clave', array_keys(self::semillas()))
                ->pluck('valor', 'clave')
                ->all();
        });

        if (! array_key_exists($clave, $mapa) || $mapa[$clave] === null || $mapa[$clave] === '') {
            return $default;
        }

        return $mapa[$clave];
    }
}
