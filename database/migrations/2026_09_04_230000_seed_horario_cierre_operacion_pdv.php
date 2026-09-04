<?php

use App\Models\ConfiguracionSistema;
use App\Services\PuntoVenta\Operacion\HorarioCierreOperacionPdvConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $config = new HorarioCierreOperacionPdvConfig;
        $inicial = $config->configuracionInicialPlaneada();

        ConfiguracionSistema::updateOrCreate(
            ['clave' => HorarioCierreOperacionPdvConfig::CLAVE],
            [
                'valor' => json_encode($inicial, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Horario de cierre operativo PDV',
            ]
        );
    }

    public function down(): void
    {
        ConfiguracionSistema::query()
            ->where('clave', HorarioCierreOperacionPdvConfig::CLAVE)
            ->delete();
    }
};
