<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones para inyectar el identificador departamental.
     */
    public function up(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->foreignId('departamento_id')
                ->nullable()
                ->after('vendedor_id')
                ->constrained('departamentos')
                ->onDelete('restrict');
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropForeign(['departamento_id']);
            $table->dropColumn('departamento_id');
        });
    }
};
