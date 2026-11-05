<?php

namespace App\Services\ControlPedidos;

use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\PedidoBma;
use Carbon\Carbon;

/** Parámetros editables; los vencimientos ya registrados conservan su fecha. */
final class ConfiguracionOperativaTienda
{
    public const CLAVE = 'control_pedidos.tienda.operacion';

    public function defaults(): array
    {
        return [
            'md_dias' => [1, 2, 3, 4, 5], 'md_corte' => '13:00',
            'apartado_dias' => [1, 2, 3, 4, 5, 6], 'apartado_cierre' => '17:00',
            'sabado_apertura' => '09:00', 'sabado_cierre' => '13:00',
            'recoleccion_dias' => 20,
        ];
    }

    public function obtener(): array
    {
        $raw = json_decode(ConfiguracionSistema::where('clave', self::CLAVE)->value('valor') ?? '{}', true);
        $datos = array_replace($this->defaults(), is_array($raw) ? array_intersect_key($raw, $this->defaults()) : []);
        foreach (['md_corte', 'apartado_cierre', 'sabado_apertura', 'sabado_cierre'] as $campo) {
            if (! is_string($datos[$campo]) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $datos[$campo])) {
                $datos[$campo] = $this->defaults()[$campo];
            }
        }
        foreach (['md_dias', 'apartado_dias'] as $campo) {
            $dias = is_array($datos[$campo]) ? array_values(array_unique(array_filter(array_map('intval', $datos[$campo]), fn ($dia) => $dia >= 1 && $dia <= 7))) : [];
            sort($dias);
            $datos[$campo] = $dias ?: $this->defaults()[$campo];
        }
        $datos['recoleccion_dias'] = max(1, min(365, (int) $datos['recoleccion_dias']));

        return $datos;
    }

    public function diasRecoleccion(?PedidoBma $pedido = null): int
    {
        return (int) ($pedido?->solicitud_preparacion_json['remision_tienda']['plazo_dias'] ?? $this->obtener()['recoleccion_dias']);
    }

    public function diaMd(Carbon $local): bool
    {
        return in_array($local->dayOfWeekIso, $this->obtener()['md_dias'], true);
    }

    public function notaMd(): string
    {
        $datos = $this->obtener();
        $nombres = [1 => 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
        $dias = implode(', ', array_map(fn ($dia) => $nombres[$dia], $datos['md_dias']));

        return 'Mismo día: transferencia reportada hasta las '.$datos['md_corte'].' ('.$dias.').';
    }
}
