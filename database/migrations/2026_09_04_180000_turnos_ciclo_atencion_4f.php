<?php

use App\Models\ConfiguracionSistema;
use App\Services\Permisos\PermisoCatalogoMigracion;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_turno_atenciones', function (Blueprint $table) {
            $table->timestamp('atencion_inicio_at')->nullable()->after('inicio_at');
            $table->index(['fin_at', 'atencion_inicio_at'], 'pdv_atenciones_fin_inicio_idx');
        });

        PermisoCatalogoMigracion::registrar([
            PuntoVentaModulo::PERMISO_TURNOS_BAJA_COLA,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            PuntoVentaModulo::PERMISO_TURNOS_TRANSFERIR,
        ]);

        $config = new PlazosTurnosPdvConfig;
        $inicial = $config->configuracionInicialAprobada();

        ConfiguracionSistema::updateOrCreate(
            ['clave' => PlazosTurnosPdvConfig::CLAVE],
            [
                'valor' => json_encode($inicial, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Plazos de espera inicial, prórroga y ventana de reatención de turnos PDV',
            ]
        );
    }

    public function down(): void
    {
        Schema::table('pdv_turno_atenciones', function (Blueprint $table) {
            $table->dropIndex('pdv_atenciones_fin_inicio_idx');
            $table->dropColumn('atencion_inicio_at');
        });

        Permission::query()->whereIn('name', [
            PuntoVentaModulo::PERMISO_TURNOS_BAJA_COLA,
            PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            PuntoVentaModulo::PERMISO_TURNOS_TRANSFERIR,
        ])->delete();

        ConfiguracionSistema::query()
            ->where('clave', PlazosTurnosPdvConfig::CLAVE)
            ->delete();
    }
};
