<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar([
            PuntoVentaModulo::PERMISO_OPERACION_PAUSA,
        ]);
    }

    public function down(): void
    {
        Permission::query()->where('name', PuntoVentaModulo::PERMISO_OPERACION_PAUSA)->delete();
    }
};
