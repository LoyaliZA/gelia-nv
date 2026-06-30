<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('catalogo_tipos_almacen', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('catalogo_marcas_producto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('almacenes', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('nombre')->constrained('sucursales')->nullOnDelete();
            $table->foreignId('tipo_almacen_id')->nullable()->after('sucursal_id')->constrained('catalogo_tipos_almacen')->nullOnDelete();
            $table->boolean('activo')->default(true)->after('tipo_almacen_id');
        });
    }

    public function down(): void
    {
        Schema::table('almacenes', function (Blueprint $table) {
            $table->dropForeign(['sucursal_id']);
            $table->dropForeign(['tipo_almacen_id']);
            $table->dropColumn(['sucursal_id', 'tipo_almacen_id', 'activo']);
        });

        Schema::dropIfExists('catalogo_marcas_producto');
        Schema::dropIfExists('catalogo_tipos_almacen');
        Schema::dropIfExists('sucursales');
    }
};
