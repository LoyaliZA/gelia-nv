<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'users',
            'sucursales',
            'clientes',
            'productos',
            'departamentos',
            'pdv_resguardos',
            'pdv_turnos',
        ] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->boolean('es_demo')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'users',
            'sucursales',
            'clientes',
            'productos',
            'departamentos',
            'pdv_resguardos',
            'pdv_turnos',
        ] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->dropColumn('es_demo');
            });
        }
    }
};
