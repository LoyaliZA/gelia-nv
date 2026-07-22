<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private const PERMISOS = [
        'traspasos.ver_listado',
        'traspasos.crear',
        'traspasos.responder',
        'traspasos.reportar_error',
        'traspasos.verificar',
        'traspasos.monitorear_alertas',
        'traspasos.reporte_dia',
        'traspasos.eliminar',
    ];

    public function up(): void
    {
        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISOS)->delete();
    }
};
