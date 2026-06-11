<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISOS = [
        'cancelaciones_cotizaciones.ver_listado',
        'cancelaciones_cotizaciones.crear',
        'cancelaciones_cotizaciones.reportar',
        'cancelaciones_cotizaciones.verificar',
        'cancelaciones_cotizaciones.solicitar_cancelacion',
        'cancelaciones_cotizaciones.cancelar',
        'cancelaciones_cotizaciones.exportar',
        'cancelaciones_cotizaciones.eliminar',
        'solicitudes.eliminar',
    ];

    public function up(): void
    {
        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::whereIn('name', self::PERMISOS)->delete();
    }
};
