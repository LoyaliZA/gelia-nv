<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISOS = [
        'clientes.direcciones.ver',
        'clientes.direcciones.crear',
        'clientes.direcciones.editar',
        'clientes.direcciones.desactivar',
        'clientes.direcciones.ver_historial',
        'clientes.direcciones.revisar_solicitudes',
        'clientes.direcciones.generar_enlace',
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
