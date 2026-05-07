<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    // 1. Departamentos (TI, Cedis, Bellaroma...)
    Schema::create('departamentos', function (Blueprint $table) {
        $table->id();
        $table->string('nombre')->unique();
        $table->boolean('activo')->default(true);
        $table->timestamps();
    });

    // 2. Áreas (Ventas, Almacén, Soporte...)
    Schema::create('areas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('departamento_id')->constrained()->onDelete('cascade');
        $table->string('nombre');
        $table->timestamps();
    });

    // 3. Relacionamos al Usuario con su Área
    Schema::table('users', function (Blueprint $table) {
        $table->foreignId('area_id')->nullable()->after('id')->constrained('areas');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizacion_tables');
    }
};
