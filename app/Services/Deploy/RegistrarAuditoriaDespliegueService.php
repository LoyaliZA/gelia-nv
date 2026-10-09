<?php

namespace App\Services\Deploy;

use App\Models\AuditoriaDespliegue;
use Illuminate\Support\Facades\Cache;

class RegistrarAuditoriaDespliegueService
{
    private const CACHE_SNAPSHOT = 'deploy.versiones.snapshot';

    private const CACHE_CONSULTAS = 'deploy.consultas_ok';

    private const CACHE_PULSO = 'deploy.consulta_pulso_at';

    private const PULSO_SEGUNDOS = 900;

    /**
     * @param  array{shell: string, surfaces: array<string, string>}  $versiones
     */
    public function registrarConsulta(array $versiones, ?int $userId = null): void
    {
        try {
            $this->registrarCambio($versiones, $userId);
            $this->registrarPulso($versiones);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function registrarEventoCliente(
        string $accion,
        string $superficie,
        ?string $version,
        ?string $detalle,
        ?int $userId = null,
    ): void {
        try {
            AuditoriaDespliegue::query()->create([
                'accion' => $accion,
                'superficie' => $superficie,
                'origen' => 'cliente',
                'user_id' => $userId,
                'detalles' => array_filter([
                    'version' => $version,
                    'detalle' => $detalle,
                    'canal' => 'vigilante_pantalla',
                ], fn ($valor) => $valor !== null && $valor !== ''),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array{shell: string, surfaces: array<string, string>}  $versiones
     */
    private function registrarCambio(array $versiones, ?int $userId): void
    {
        $actual = json_encode($versiones);
        $anterior = Cache::get(self::CACHE_SNAPSHOT);

        if (is_string($anterior) && $anterior !== $actual) {
            $previo = json_decode($anterior, true);
            AuditoriaDespliegue::query()->create([
                'accion' => 'publicacion_detectada',
                'superficie' => null,
                'origen' => 'servidor',
                'user_id' => $userId,
                'detalles' => [
                    'anterior' => is_array($previo) ? $previo : null,
                    'actual' => $versiones,
                    'canal' => 'vigilante_pantalla',
                ],
            ]);
        }

        Cache::forever(self::CACHE_SNAPSHOT, $actual);
    }

    /**
     * @param  array{shell: string, surfaces: array<string, string>}  $versiones
     */
    private function registrarPulso(array $versiones): void
    {
        if (! Cache::has(self::CACHE_CONSULTAS)) {
            Cache::forever(self::CACHE_CONSULTAS, 0);
        }
        $consultas = (int) Cache::increment(self::CACHE_CONSULTAS);
        $pulso = Cache::get(self::CACHE_PULSO);
        $ahora = now()->getTimestamp();

        if (is_numeric($pulso) && ($ahora - (int) $pulso) < self::PULSO_SEGUNDOS) {
            return;
        }

        AuditoriaDespliegue::query()->create([
            'accion' => 'consulta_ok',
            'superficie' => null,
            'origen' => 'servidor',
            'user_id' => null,
            'detalles' => [
                'consultas' => $consultas,
                'canal' => 'vigilante_pantalla',
                'shell' => $versiones['shell'],
                'surfaces' => $versiones['surfaces'],
            ],
        ]);

        Cache::forever(self::CACHE_PULSO, $ahora);
        Cache::forever(self::CACHE_CONSULTAS, 0);
    }
}
