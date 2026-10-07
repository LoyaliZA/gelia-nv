<?php

use App\Models\ConfiguracionSistema;
use App\Services\Escalonamiento\EscalonamientoAutoridadConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        foreach (EscalonamientoAutoridadConfig::semillas() as $clave => $meta) {
            ConfiguracionSistema::updateOrCreate(
                ['clave' => $clave],
                [
                    'valor' => $meta['valor'],
                    'tipo' => $meta['tipo'],
                    'grupo' => 'Escalonamiento',
                    'descripcion' => $meta['descripcion'],
                ],
            );
        }

        Cache::forget('configuraciones_sistema_globales');
        Cache::forget(EscalonamientoAutoridadConfig::CACHE_KEY);
    }

    public function down(): void
    {
        ConfiguracionSistema::query()
            ->whereIn('clave', array_keys(EscalonamientoAutoridadConfig::semillas()))
            ->delete();

        Cache::forget('configuraciones_sistema_globales');
        Cache::forget(EscalonamientoAutoridadConfig::CACHE_KEY);
    }
};
