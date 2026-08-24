<?php

namespace App\Services\ControlPedidos;

use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Cache;

class EnviosPedidoBmaConfig
{
    public const CLAVE_DETALLE = 'control_pedidos.envios.detalle_cajas';

    public const CLAVE_MONEDA = 'control_pedidos.envios.moneda';

    public const CLAVE_REEXPEDICION = 'control_pedidos.envios.reexpedicion_alcance';

    public const CLAVE_CEDIS_COSTO = 'control_pedidos.envios.cedis_captura_costo';

    public const CLAVE_EDITAR_RECOLECCION = 'control_pedidos.envios.editar_tras_recoleccion';

    public const CLAVE_PRECISION = 'control_pedidos.envios.precision_decimales';

    public const CLAVE_REQUISITOS_PAQ = 'control_pedidos.envios.requisitos_paqueteria';

    public const CACHE_KEY = 'control_pedidos.envios.config';

    public const ALCANCE_PEDIDO = 'pedido';

    public const ALCANCE_CAJA = 'caja';

    /**
     * @return array<string, array{valor: string, tipo: string, descripcion: string}>
     */
    public static function semillas(): array
    {
        return [
            self::CLAVE_DETALLE => [
                'valor' => '0',
                'tipo' => 'boolean',
                'descripcion' => 'UI de tarjetas de envío con costos por caja (Control Pedidos)',
            ],
            self::CLAVE_MONEDA => [
                'valor' => 'MXN',
                'tipo' => 'string',
                'descripcion' => 'Moneda predeterminada de costos de envío por caja',
            ],
            self::CLAVE_REEXPEDICION => [
                'valor' => self::ALCANCE_PEDIDO,
                'tipo' => 'string',
                'descripcion' => 'Alcance de reexpedición: pedido o caja',
            ],
            self::CLAVE_CEDIS_COSTO => [
                'valor' => '0',
                'tipo' => 'boolean',
                'descripcion' => 'Permitir que CEDIS capture costo comercial de envío',
            ],
            self::CLAVE_EDITAR_RECOLECCION => [
                'valor' => '0',
                'tipo' => 'boolean',
                'descripcion' => 'Permitir editar peso/costo de una caja ya recolectada',
            ],
            self::CLAVE_PRECISION => [
                'valor' => '2',
                'tipo' => 'integer',
                'descripcion' => 'Decimales de redondeo de costos de envío',
            ],
            self::CLAVE_REQUISITOS_PAQ => [
                'valor' => '{}',
                'tipo' => 'json',
                'descripcion' => 'Requisitos de evidencia por catalogo_paqueteria_id (JSON)',
            ],
        ];
    }

    public function detalleCajas(): bool
    {
        return $this->bool(self::CLAVE_DETALLE, false);
    }

    public function moneda(): string
    {
        $raw = strtoupper(trim((string) $this->valor(self::CLAVE_MONEDA, 'MXN')));

        return preg_match('/^[A-Z]{3}$/', $raw) ? $raw : 'MXN';
    }

    public function reexpedicionAlcance(): string
    {
        $raw = (string) $this->valor(self::CLAVE_REEXPEDICION, self::ALCANCE_PEDIDO);

        return in_array($raw, [self::ALCANCE_PEDIDO, self::ALCANCE_CAJA], true)
            ? $raw
            : self::ALCANCE_PEDIDO;
    }

    public function cedisCapturaCosto(): bool
    {
        return $this->bool(self::CLAVE_CEDIS_COSTO, false);
    }

    public function editarTrasRecoleccion(): bool
    {
        return $this->bool(self::CLAVE_EDITAR_RECOLECCION, false);
    }

    public function precision(): int
    {
        $n = (int) $this->valor(self::CLAVE_PRECISION, 2);

        return max(0, min(4, $n));
    }

    /**
     * @return array<string, mixed>
     */
    public function requisitosPaqueteria(): array
    {
        $raw = $this->valor(self::CLAVE_REQUISITOS_PAQ, '{}');
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{
     *   detalle_cajas: bool,
     *   moneda: string,
     *   reexpedicion_alcance: string,
     *   cedis_captura_costo: bool,
     *   editar_tras_recoleccion: bool,
     *   precision_decimales: int,
     *   requisitos_paqueteria: array<string, mixed>
     * }
     */
    public function todas(): array
    {
        return [
            'detalle_cajas' => $this->detalleCajas(),
            'moneda' => $this->moneda(),
            'reexpedicion_alcance' => $this->reexpedicionAlcance(),
            'cedis_captura_costo' => $this->cedisCapturaCosto(),
            'editar_tras_recoleccion' => $this->editarTrasRecoleccion(),
            'precision_decimales' => $this->precision(),
            'requisitos_paqueteria' => $this->requisitosPaqueteria(),
        ];
    }

    /**
     * Matriz actor × concepto para el frontend. El backend revalida.
     *
     * @return array{peso: bool, costo: bool, retirar: bool}
     */
    public function matrizActor(string $actor, ?string $fase = null, bool $pagoValidado = false): array
    {
        $cedis = $actor === 'cedis';
        $ventas = $actor === 'ventas';
        $auxiliar = $actor === 'auxiliar';

        return [
            'peso' => $cedis,
            'costo' => $ventas && ! $pagoValidado,
            'retirar' => $cedis || $ventas,
            'solo_lectura' => $auxiliar || ($pagoValidado && ! $ventas),
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
