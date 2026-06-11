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
        Schema::create('auditorias_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Quién hace el cambio
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete(); // A quién se le hace el cambio
            $table->string('modulo'); // Ej: Usuarios, Perfil, Roles
            $table->string('accion'); // Ej: Asignación de permisos, Actualización de perfil
            $table->json('detalles')->nullable(); // Snapshot JSON
            $table->timestamps(3); // con precisión de milisegundos si lo permite, o estándar
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditorias_configuraciones');
    }
};
