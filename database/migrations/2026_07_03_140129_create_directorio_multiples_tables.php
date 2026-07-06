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
        // Drop old table if exists
        Schema::dropIfExists('directorio_contactos');

        Schema::create('directorio_correos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rh_colaborador_id')->nullable()->constrained('rh_colaboradores')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('directorio_telefonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rh_colaborador_id')->nullable()->constrained('rh_colaboradores')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('telefono');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('directorio_extensiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('rh_colaborador_id')->nullable()->constrained('rh_colaboradores')->nullOnDelete();
            $table->string('extension');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('directorio_extensiones');
        Schema::dropIfExists('directorio_telefonos');
        Schema::dropIfExists('directorio_correos');

        // Recrear si hace rollback
        Schema::create('directorio_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rh_colaborador_id')->nullable()->constrained('rh_colaboradores')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('extension')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
