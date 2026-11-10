<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones_clientes', function (Blueprint $table) {
            $table->string('estado', 20)->nullable();
            $table->json('resumen')->nullable();
            $table->text('mensaje_fatal')->nullable();
        });
        Schema::table('errores_importacion_clientes', fn (Blueprint $table) => $table->string('campo')->nullable());
    }

    public function down(): void
    {
        Schema::table('errores_importacion_clientes', fn (Blueprint $table) => $table->dropColumn('campo'));
        Schema::table('importaciones_clientes', fn (Blueprint $table) => $table->dropColumn(['estado', 'resumen', 'mensaje_fatal']));
    }
};
