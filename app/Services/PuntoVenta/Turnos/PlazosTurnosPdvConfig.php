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
     *   ventana_reatencion_minutos: int,
     *   aviso_tolerancia_espera_minutos: int,
     *   aviso_tolerancia_prorroga_minutos: int,
     *   inicio_atencion_automatico: bool
     * }
     */
    public function configuracionInicialAprobada(): array
    {
        return [
            'espera_inicial_minutos' => 5,
            'prorroga_minutos' => 20,
            'ventana_reatencion_minutos' => 90,
            'aviso_tolerancia_espera_minutos' => max(1, (int) config('pdv_alertas.aviso_previo_minutos.espera_inicial', 1)),
            'aviso_tolerancia_prorroga_minutos' => max(1, (int) config('pdv_alertas.aviso_previo_minutos.prorroga', 2)),
            'inicio_atencion_automatico' => false,
        ];
    }

    public function estaConfigurado(): bool
    {
        return ConfiguracionSistema::query()->where('clave', self::CLAVE)->exists();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int,
     *   aviso_tolerancia_espera_minutos: int,
     *   aviso_tolerancia_prorroga_minutos: int,
     *   inicio_atencion_automatico: bool
     * }
     */
    public function persistir(array $datos): array
    {
        $normalizado = $this->normalizar($datos);

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => self::CLAVE],
            [
                'valor' => json_encode($normalizado, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Plazos de espera inicial, prórroga, tolerancias de aviso y ventana de reatención de turnos PDV',
            ]
        );

        Cache::forget(self::CACHE_KEY);

        return $normalizado;
    }

    /**
     * @return array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int,
     *   aviso_tolerancia_espera_minutos: int,
     *   aviso_tolerancia_prorroga_minutos: int,
     *   inicio_atencion_automatico: bool
     * }
     */
    public function obtener(): array
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('Los plazos operativos de turnos PDV no están configurados.');
        }

        return Cache::rememberForever(self::CACHE_KEY, function () {
            return $this->leerFila();
        });
    }

    /**
     * @return array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int,
     *   aviso_tolerancia_espera_minutos: int,
     *   aviso_tolerancia_prorroga_minutos: int,
     *   inicio_atencion_automatico: bool
     * }
     */
    public function obtenerOPredeterminado(): array
    {
        if (! $this->estaConfigurado()) {
            return $this->configuracionInicialAprobada();
        }

        return $this->obtener();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int,
     *   aviso_tolerancia_espera_minutos: int,
     *   aviso_tolerancia_prorroga_minutos: int,
     *   inicio_atencion_automatico: bool
     * }
     */
    private function normalizar(array $datos): array
    {
        $base = $this->configuracionInicialAprobada();

        foreach (array_keys($base) as $clave) {
            if ($clave === 'inicio_atencion_automatico') {
                if (array_key_exists($clave, $datos)) {
                    $base[$clave] = filter_var($datos[$clave], FILTER_VALIDATE_BOOLEAN);
                }

                continue;
            }

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

    /**
     * @return array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int,
     *   aviso_tolerancia_espera_minutos: int,
     *   aviso_tolerancia_prorroga_minutos: int,
     *   inicio_atencion_automatico: bool
     * }
     */
    private function leerFila(): array
    {
        $row = ConfiguracionSistema::query()->where('clave', self::CLAVE)->first();
        $raw = $row?->valor;
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);

        return $this->normalizar(is_array($decoded) ? $decoded : []);
    }
}
