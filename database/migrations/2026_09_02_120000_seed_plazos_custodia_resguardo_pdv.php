<?php

use App\Models\ConfiguracionSistema;
use App\Services\PuntoVenta\Resguardos\PlazosCustodiaResguardoPdvConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $config = new PlazosCustodiaResguardoPdvConfig;
        $inicial = $config->configuracionInicialAprobada();

        ConfiguracionSistema::updateOrCreate(
            ['clave' => PlazosCustodiaResguardoPdvConfig::CLAVE],
            [
                'valor' => json_encode($inicial, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Plazos de custodia, aviso previo y rezago de resguardos PDV',
            ]
        );
    }

    public function down(): void
    {
        ConfiguracionSistema::query()
            ->where('clave', PlazosCustodiaResguardoPdvConfig::CLAVE)
            ->delete();
    }
};
