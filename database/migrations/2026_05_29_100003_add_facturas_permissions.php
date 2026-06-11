<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISOS = [
        'facturas.ver_listado',
        'facturas.crear',
        'facturas.responder',
        'facturas.verificar',
        'facturas.eliminar',
        'facturas.gestionar_datos_fiscales',
        'facturas.exportar',
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
