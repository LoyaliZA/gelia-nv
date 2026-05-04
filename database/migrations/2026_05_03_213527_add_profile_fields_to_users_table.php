<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Creación estricta de tabla catálogo para evitar ENUMs
        Schema::create('catalogo_sexos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        // 2. Modificación de la tabla usuarios existente
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name')->nullable();
            $table->string('apellido_paterno')->after('username')->nullable();
            $table->string('apellido_materno')->after('apellido_paterno')->nullable();
            $table->string('telefono')->nullable();
            $table->integer('edad')->nullable();
            $table->string('foto_perfil')->nullable();
            $table->foreignId('catalogo_sexo_id')->nullable()->constrained('catalogo_sexos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['catalogo_sexo_id']);
            $table->dropColumn([
                'username', 'apellido_paterno', 'apellido_materno', 
                'telefono', 'edad', 'foto_perfil', 'catalogo_sexo_id'
            ]);
        });
        Schema::dropIfExists('catalogo_sexos');
    }
};