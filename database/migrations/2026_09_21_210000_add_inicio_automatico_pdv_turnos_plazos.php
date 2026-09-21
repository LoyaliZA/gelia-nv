<?php

use App\Models\ConfiguracionSistema;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        $config = new PlazosTurnosPdvConfig;
        $defaults = $config->configuracionInicialAprobada();

        $row = ConfiguracionSistema::query()->where('clave', PlazosTurnosPdvConfig::CLAVE)->first();
        $decoded = [];
        if ($row !== null) {
            $raw = $row->valor;
            $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
            if (! is_array($decoded)) {
                $decoded = [];
            }
        }

        $mergeado = array_merge($defaults, $decoded);

        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PlazosTurnosPdvConfig::CLAVE],
            [
                'valor' => json_encode($mergeado, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Plazos de espera inicial, prórroga, tolerancias de aviso, ventana de reatención e inicio automático de turnos PDV',
            ]
        );

        Cache::forget(PlazosTurnosPdvConfig::CACHE_KEY);
    }

    public function down(): void
    {
        $row = ConfiguracionSistema::query()->where('clave', PlazosTurnosPdvConfig::CLAVE)->first();
        if ($row === null) {
            return;
        }

        $raw = $row->valor;
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
        if (! is_array($decoded)) {
            return;
        }

        unset($decoded['inicio_atencion_automatico']);

        $row->valor = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        $row->descripcion = 'Plazos de espera inicial, prórroga, tolerancias de aviso y ventana de reatención de turnos PDV';
        $row->save();

        Cache::forget(PlazosTurnosPdvConfig::CACHE_KEY);
    }
};
