<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISOS = [
        'listados.guardar_generado',
        'listados.enviar',
        'listados.visualizar',
    ];

    public function up(): void
    {
        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        // Los permisos se retiran desde administración; no eliminar del catálogo en down.
    }
};
