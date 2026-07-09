<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISOS = [
        'control_pedidos.ver_listado',
        'control_pedidos.crear',
        'control_pedidos.editar',
        'control_pedidos.eliminar',
        'control_pedidos.exportar',
        'control_pedidos.ver_detalle',
        'control_pedidos.configurar_catalogos',
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
