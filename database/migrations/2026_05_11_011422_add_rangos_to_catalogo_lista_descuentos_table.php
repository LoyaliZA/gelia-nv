<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            // Agregamos los rangos para automatizar las sugerencias en el frontend
            $table->decimal('monto_minimo', 15, 2)->default(0)->after('monto_requerido');
            $table->decimal('monto_maximo', 15, 2)->default(0)->after('monto_minimo');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->dropColumn(['monto_minimo', 'monto_maximo']);
        });
    }
};