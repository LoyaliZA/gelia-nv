<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_pantalla_publicidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->cascadeOnDelete();
            $table->string('tipo', 16);
            $table->string('ruta');
            $table->unsignedSmallInteger('duracion_seg')->nullable();
            $table->string('ajuste', 16)->default('cover');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamp('vigente_desde')->nullable();
            $table->timestamp('vigente_hasta')->nullable();
            $table->string('nombre_original')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['activa', 'sucursal_id', 'orden'], 'pdv_pantalla_pub_playlist_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_pantalla_publicidades');
    }
};
