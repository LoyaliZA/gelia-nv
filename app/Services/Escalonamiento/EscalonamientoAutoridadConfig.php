<?php

namespace App\Services\Escalonamiento;

use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Cache;

class EscalonamientoAutoridadConfig
{
    public const CLAVE_AUTORIDAD = 'escalonamiento.autoridad_activa';

    public const CLAVE_PERIODO_OFICIAL = 'escalonamiento.periodo_oficial_id';

    public const CACHE_KEY = 'escalonamiento.autoridad.config';

    /**
     * @return array<string, array{valor: string, tipo: string, descripcion: string}>
     */
    public static function semillas(): array
    {
        return [
            self::CLAVE_AUTORIDAD => [
                'valor' => '0',
                'tipo' => 'boolean',
                'descripcion' => 'El módulo de escalonamiento publica monto y lista vigente en clientes (Etapa 5). Activar solo tras el corte del runbook.',
            ],
            self::CLAVE_PERIODO_OFICIAL => [
                'valor' => '',
                'tipo' => 'integer',
                'descripcion' => 'ID del período abierto que gobierna la proyección. Vacío = último período abierto por año/mes.',
            ],
        ];
    }

    public function estaActiva(): bool
    {
        return $this->bool(self::CLAVE_AUTORIDAD, (bool) config('escalonamiento.autoridad_activa', false));
    }

    public function periodoOficialId(): ?int
    {
        $id = config('escalonamiento.periodo_oficial_id');
        if ($id) {
            return (int) $id;
        }

        $raw = $this->valor(self::CLAVE_PERIODO_OFICIAL, '');
        if ($raw === '' || $raw === null) {
            return null;
        }

        return (int) $raw;
    }

    /**
     * @return array{autoridad_activa: bool, periodo_oficial_id: ?int}
     */
    public function estado(): array
    {
        return [
            'autoridad_activa' => $this->estaActiva(),
            'periodo_oficial_id' => $this->periodoOficialId(),
        ];
    }

    public function guardar(bool $autoridadActiva, ?int $periodoOficialId = null): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => self::CLAVE_AUTORIDAD],
            [
                'valor' => $autoridadActiva ? '1' : '0',
                'tipo' => 'boolean',
                'grupo' => 'Escalonamiento',
                'descripcion' => self::semillas()[self::CLAVE_AUTORIDAD]['descripcion'],
            ],
        );

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => self::CLAVE_PERIODO_OFICIAL],
            [
                'valor' => $periodoOficialId ? (string) $periodoOficialId : '',
                'tipo' => 'integer',
                'grupo' => 'Escalonamiento',
                'descripcion' => self::semillas()[self::CLAVE_PERIODO_OFICIAL]['descripcion'],
            ],
        );

        $this->olvidarCache();

        config([
            'escalonamiento.autoridad_activa' => $autoridadActiva,
            'escalonamiento.periodo_oficial_id' => $periodoOficialId,
        ]);
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

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
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
