<?php

use App\Models\ConfiguracionSistema;
use App\Services\PuntoVenta\Resguardos\RegistroManualResguardoPdvConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        ConfiguracionSistema::updateOrCreate(
            ['clave' => RegistroManualResguardoPdvConfig::CLAVE],
            [
                'valor' => '0',
                'tipo' => 'boolean',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Habilita el registro manual de resguardos en sucursal',
            ]
        );

        Cache::forget('configuraciones_sistema_globales');
    }

    public function down(): void
    {
        ConfiguracionSistema::query()
            ->where('clave', RegistroManualResguardoPdvConfig::CLAVE)
            ->delete();

        Cache::forget('configuraciones_sistema_globales');
    }
};
