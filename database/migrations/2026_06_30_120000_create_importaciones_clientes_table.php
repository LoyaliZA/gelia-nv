<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('nombre_archivo_original');
            $table->string('ruta_almacenamiento');
            $table->unsignedInteger('filas_leidas')->default(0);
            $table->unsignedInteger('filas_procesadas')->default(0);
            $table->unsignedInteger('filas_omitidas')->default(0);
            $table->unsignedInteger('errores')->default(0);
            $table->unsignedInteger('ascensos')->default(0);
            $table->unsignedInteger('clientes_marcados_inactivos')->default(0);
            $table->decimal('duracion_seg', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones_clientes');
    }
};
