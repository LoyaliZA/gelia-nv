<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Relación Muchos a Muchos: Usuarios <-> Departamentos
        Schema::create('departamento_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // 2. Relación Muchos a Muchos: Usuarios <-> Áreas
        Schema::create('area_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // 3. Relación Matricial: Gerentes <-> Colaboradores
        Schema::create('gerente_colaborador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gerente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('colaborador_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // 4. Limpieza del esquema obsoleto
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->constrained('areas');
        });

        Schema::dropIfExists('gerente_colaborador');
        Schema::dropIfExists('area_user');
        Schema::dropIfExists('departamento_user');
    }
};