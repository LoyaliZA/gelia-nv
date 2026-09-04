<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PlazosTurnosPdvConfig
{
    public const CLAVE = 'pdv.turnos.plazos';

    public const CACHE_KEY = 'pdv.turnos.plazos';

    /**
     * Valores aprobados en CONTRATO_TURNOS_PDV §8–9 — solo para migración/seeder.
     *
     * @return array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int
     * }
     */
    public function configuracionInicialAprobada(): array
    {
        return [
            'espera_inicial_minutos' => 5,
            'prorroga_minutos' => 20,
            'ventana_reatencion_minutos' => 90,
        ];
    }

    public function estaConfigurado(): bool
    {
        return ConfiguracionSistema::query()->where('clave', self::CLAVE)->exists();
    }

    /**
     * @return array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int
     * }
     */
    public function obtener(): array
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('Los plazos operativos de turnos PDV no están configurados.');
        }

        return Cache::rememberForever(self::CACHE_KEY, function () {
            $row = ConfiguracionSistema::query()->where('clave', self::CLAVE)->first();
            $raw = $row?->valor;
            $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);

            return $this->normalizar(is_array($decoded) ? $decoded : []);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int
     * }
     */
    private function normalizar(array $datos): array
    {
        $base = $this->configuracionInicialAprobada();

        foreach (array_keys($base) as $clave) {
            if (! array_key_exists($clave, $datos) || ! is_numeric($datos[$clave])) {
                continue;
            }

            $valor = (int) $datos[$clave];
            if ($valor > 0) {
                $base[$clave] = $valor;
            }
        }

        return $base;
    }
}
