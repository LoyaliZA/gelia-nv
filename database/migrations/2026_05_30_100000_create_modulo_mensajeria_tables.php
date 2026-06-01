<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversaciones', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['directo', 'grupo']);
            $table->string('nombre')->nullable();
            $table->string('foto')->nullable();
            $table->foreignId('creado_por')->constrained('users');
            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->string('ultimo_mensaje_preview', 500)->nullable();
            $table->timestamps();

            $table->index('ultimo_mensaje_at');
        });

        Schema::create('conversacion_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('conversaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('rol', ['admin', 'miembro'])->default('miembro');
            $table->timestamp('ultimo_leido_at')->nullable();
            $table->boolean('silenciado')->default(false);
            $table->timestamps();

            $table->unique(['conversacion_id', 'user_id']);
            $table->index(['user_id', 'conversacion_id']);
        });

        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('conversaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('tipo', ['texto', 'imagen', 'video', 'audio', 'archivo']);
            $table->text('contenido')->nullable();
            $table->foreignId('reply_to_id')->nullable()->constrained('mensajes')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['conversacion_id', 'created_at', 'id']);
        });

        Schema::create('mensaje_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensaje_id')->constrained('mensajes')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('thumbnail_ruta')->nullable();
            $table->string('nombre_original')->nullable();
            $table->string('mime', 100);
            $table->unsignedBigInteger('tamano')->default(0);
            $table->unsignedInteger('duracion_seg')->nullable();
            $table->json('metadata')->nullable();
            $table->text('contenido_indexado')->nullable();
            $table->timestamps();

            $table->index('mensaje_id');
        });

        Schema::create('mensaje_lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensaje_id')->constrained('mensajes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('leido_at');

            $table->unique(['mensaje_id', 'user_id']);
            $table->index(['mensaje_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensaje_lecturas');
        Schema::dropIfExists('mensaje_adjuntos');
        Schema::dropIfExists('mensajes');
        Schema::dropIfExists('conversacion_participantes');
        Schema::dropIfExists('conversaciones');
    }
};
