<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursal_almacen_origen_traspaso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sucursal_id', 'almacen_id'], 'suc_alm_orig_trasp_uq');
        });

        Schema::table('solicitudes_traspasos', function (Blueprint $table) {
            $table->foreignId('sucursal_solicitante_id')
                ->nullable()
                ->after('departamento_id')
                ->constrained('sucursales')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_traspasos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_solicitante_id');
        });

        Schema::dropIfExists('sucursal_almacen_origen_traspaso');
    }
};
