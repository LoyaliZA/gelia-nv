<?php

use App\Models\ConfiguracionSistema;
use App\Services\Permisos\PermisoCatalogoMigracion;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar(PuntoVentaModulo::permisosIniciales());

        ConfiguracionSistema::updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            [
                'valor' => '0',
                'tipo' => 'boolean',
                'grupo' => 'PuntoVenta',
                'descripcion' => 'Habilita las rutas del módulo Punto de Venta',
            ]
        );

        Cache::forget('configuraciones_sistema_globales');
    }

    public function down(): void
    {
        ConfiguracionSistema::where('clave', PuntoVentaModulo::CLAVE_FLAG)->delete();
        Cache::forget('configuraciones_sistema_globales');

        $quitar = array_values(array_filter(
            PuntoVentaModulo::permisosIniciales(),
            fn (string $permiso): bool => $permiso !== AlcancePdv::PERMISO_ALCANCE_GLOBAL
        ));

        Permission::whereIn('name', $quitar)->delete();
    }
};
