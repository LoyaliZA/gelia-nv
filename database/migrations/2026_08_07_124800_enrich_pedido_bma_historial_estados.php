<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_historial_estados', function (Blueprint $table) {
            $table->string('accion', 80)->nullable()->after('usuario_id');
            $table->string('rol', 120)->nullable()->after('accion');
            $table->string('departamento', 120)->nullable()->after('rol');
            $table->string('evidencia_ruta')->nullable()->after('comentarios');
            $table->string('evidencia_nombre')->nullable()->after('evidencia_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_bma_historial_estados', function (Blueprint $table) {
            $table->dropColumn(['accion', 'rol', 'departamento', 'evidencia_ruta', 'evidencia_nombre']);
        });
    }
};
