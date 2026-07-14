<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_direcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->unsignedInteger('numero_direccion');
            $table->string('etiqueta')->nullable();
            $table->string('tipo_direccion', 50)->nullable();
            $table->string('nombre_destinatario');
            $table->string('telefono_destinatario', 30)->nullable();
            $table->string('calle')->nullable();
            $table->string('numero_exterior', 30)->nullable();
            $table->string('numero_interior', 30)->nullable();
            $table->string('colonia')->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('municipio')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado')->nullable();
            $table->string('pais')->nullable();
            $table->text('referencias')->nullable();
            $table->text('indicaciones_entrega')->nullable();
            $table->boolean('es_principal')->default(false);
            $table->boolean('esta_activa')->default(true);
            $table->string('estado_verificacion', 40)->default('pending');
            $table->string('origen', 40)->default('manual');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('direccion_anterior_id')
                ->nullable()
                ->constrained('cliente_direcciones')
                ->nullOnDelete();
            $table->timestamp('verificada_en')->nullable();
            $table->foreignId('verificada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'esta_activa']);
            $table->index(['cliente_id', 'estado_verificacion']);
            $table->index(['cliente_id', 'es_principal']);
            $table->index('codigo_postal');
            $table->unique(['cliente_id', 'numero_direccion', 'version'], 'cliente_direcciones_cliente_numero_version_unique');
        });

        Schema::create('enlaces_direccion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('accion_permitida', 40)->nullable();
            $table->foreignId('direccion_id')->nullable()->constrained('cliente_direcciones')->nullOnDelete();
            $table->timestamp('expira_en')->nullable();
            $table->timestamp('usado_en')->nullable();
            $table->timestamp('revocado_en')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cliente_id', 'revocado_en']);
        });

        Schema::create('solicitudes_direccion', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 40)->unique();
            $table->foreignId('token_publico_id')->nullable()->constrained('enlaces_direccion')->nullOnDelete();
            $table->string('numero_cliente_declarado')->nullable();
            $table->foreignId('cliente_coincidente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('accion_solicitada', 40);
            $table->foreignId('direccion_seleccionada_id')->nullable()->constrained('cliente_direcciones')->nullOnDelete();
            $table->string('nombre_declarado')->nullable();
            $table->string('telefono_declarado', 30)->nullable();
            $table->string('correo_declarado')->nullable();
            $table->json('datos_solicitados_json')->nullable();
            $table->boolean('anexa_remision')->default(false);
            $table->string('archivo_remision')->nullable();
            $table->string('estado', 40)->default('pending');
            $table->text('notas_validacion')->nullable();
            $table->string('estado_remision', 40)->nullable();
            $table->foreignId('revisada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisada_en')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index('cliente_coincidente_id');
            $table->index('estado_remision');
        });

        Schema::create('cliente_direccion_auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('cliente_direccion_id')->nullable()->constrained('cliente_direcciones')->nullOnDelete();
            $table->foreignId('solicitud_direccion_id')->nullable()->constrained('solicitudes_direccion')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 60);
            $table->string('origen', 40)->nullable();
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_direccion_auditorias');
        Schema::dropIfExists('solicitudes_direccion');
        Schema::dropIfExists('enlaces_direccion');
        Schema::dropIfExists('cliente_direcciones');
    }
};
