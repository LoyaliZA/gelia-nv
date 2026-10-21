<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->integer('posicion');
            $table->unsignedBigInteger('principal_producto_id')->nullable();
            $table->unsignedBigInteger('lote_archivo_id')->nullable();
            $table->string('clave_original');
            $table->string('clave_miniatura')->nullable();
            $table->string('mime', 127);
            $table->unsignedBigInteger('tamano_bytes');
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();
            $table->string('estado', 32);
            $table->timestamps();

            $table->unique(['producto_id', 'posicion']);
            $table->unique('principal_producto_id');
            $table->unique('lote_archivo_id');
        });

        Schema::create('producto_imagen_lotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 32)->default('revision');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['estado', 'expires_at']);
        });

        Schema::create('producto_imagen_lote_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('producto_imagen_lotes')->cascadeOnDelete();
            $table->unsignedInteger('indice');
            $table->string('nombre_original');
            $table->unsignedInteger('folio_detectado')->nullable();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('imagen_id')->nullable()->constrained('producto_imagenes')->nullOnDelete();
            $table->string('estado', 32);
            $table->string('clasificacion', 32)->nullable();
            $table->text('motivo')->nullable();
            $table->string('apoyo_sku')->nullable();
            $table->json('candidatos')->nullable();
            $table->string('hash_contenido', 64)->nullable();
            $table->string('clave_temporal')->nullable();
            $table->timestamp('reclamado_en')->nullable();
            $table->timestamps();

            $table->unique(['lote_id', 'indice']);
            $table->index(['lote_id', 'estado']);
        });

        Schema::create('producto_imagen_limpiezas', function (Blueprint $table) {
            $table->id();
            $table->string('clave');
            $table->unsignedInteger('intentos')->default(0);
            $table->timestamps();

            $table->index('clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_imagen_limpiezas');
        Schema::dropIfExists('producto_imagen_lote_archivos');
        Schema::dropIfExists('producto_imagen_lotes');
        Schema::dropIfExists('producto_imagenes');
    }
};
