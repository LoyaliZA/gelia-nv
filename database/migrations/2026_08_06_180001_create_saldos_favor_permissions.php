<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private const PERMISOS = [
        'saldos_favor.ver',
        'saldos_favor.generar',
        'saldos_favor.aplicar',
        'saldos_favor.revisar',
        'saldos_favor.ajustar',
        'saldos_favor.cancelar',
        'saldos_favor.configurar',
        'saldos_favor.caja',
        'saldos_favor.migrar',
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
