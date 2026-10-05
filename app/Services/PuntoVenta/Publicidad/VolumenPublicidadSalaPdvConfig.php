<?php

namespace App\Services\PuntoVenta\Publicidad;

use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Cache;

final class VolumenPublicidadSalaPdvConfig
{
    public const CLAVE = 'pdv.publicidad.volumen_sala';

    public const CACHE_KEY = 'pdv.publicidad.volumen_sala';

    public const PORCENTAJE_DEFECTO = 35;

    public function porcentaje(?int $sucursalId): int
    {
        $mapa = $this->porSucursal();
        if ($sucursalId === null) {
            return self::PORCENTAJE_DEFECTO;
        }

        $valor = $mapa[(string) $sucursalId] ?? null;

        return $this->normalizar($valor);
    }

    public function fraccion(?int $sucursalId): float
    {
        return round($this->porcentaje($sucursalId) / 100, 2);
    }

    public function guardar(int $sucursalId, int $porcentaje): int
    {
        $normalizado = $this->normalizar($porcentaje);
        $mapa = $this->porSucursal();
        $mapa[(string) $sucursalId] = $normalizado;

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => self::CLAVE],
            [
                'valor' => json_encode(['por_sucursal' => $mapa], JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Volumen de videos de publicidad en la pantalla de turnos',
            ]
        );

        Cache::forget(self::CACHE_KEY);

        return $normalizado;
    }

    /**
     * @return array<string, int>
     */
    private function porSucursal(): array
    {
        /** @var array<string, int> $mapa */
        $mapa = Cache::rememberForever(self::CACHE_KEY, function (): array {
            $row = ConfiguracionSistema::query()->where('clave', self::CLAVE)->first();
            $raw = $row?->valor;
            $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
            $origen = is_array($decoded) ? ($decoded['por_sucursal'] ?? []) : [];
            if (! is_array($origen)) {
                return [];
            }

            $normalizado = [];
            foreach ($origen as $sucursal => $valor) {
                $normalizado[(string) $sucursal] = $this->normalizar($valor);
            }

            return $normalizado;
        });

        return $mapa;
    }

    private function normalizar(mixed $valor): int
    {
        if (! is_numeric($valor)) {
            return self::PORCENTAJE_DEFECTO;
        }

        return max(0, min(100, (int) $valor));
    }
}
