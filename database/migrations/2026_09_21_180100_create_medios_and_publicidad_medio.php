<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medios', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nombre_original');
            $table->string('object_key');
            $table->string('mime_type', 127);
            $table->string('extension', 16);
            $table->unsignedBigInteger('tamano_bytes');
            $table->unsignedInteger('duracion_seg')->nullable();
            $table->string('tipo', 16);
            $table->string('estado', 24)->default('ready');
            $table->string('proposito', 64);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['proposito', 'estado']);
        });

        Schema::create('medio_cargas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('medio_id')->nullable()->constrained('medios')->nullOnDelete();
            $table->string('r2_upload_id')->nullable();
            $table->string('object_key');
            $table->string('nombre_original');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('tamano_bytes');
            $table->string('upload_type', 16);
            $table->unsignedInteger('chunk_size')->nullable();
            $table->string('estado', 24)->default('pending');
            $table->string('proposito', 64);
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'expires_at'], 'medio_cargas_estado_expira_idx');
            $table->index('subido_por');
        });

        Schema::table('pdv_pantalla_publicidades', function (Blueprint $table) {
            $table->foreignId('medio_id')->nullable()->after('sucursal_id')->constrained('medios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdv_pantalla_publicidades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('medio_id');
        });
        Schema::dropIfExists('medio_cargas');
        Schema::dropIfExists('medios');
    }
};
