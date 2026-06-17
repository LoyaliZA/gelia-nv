<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soporte_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->time('horario_inicio')->default('09:00:00');
            $table->time('horario_fin')->default('17:00:00');
            $table->string('mensaje_fuera_horario')->nullable();
            $table->time('hora_notificacion_diaria')->default('09:30:00');
            $table->timestamps();
        });

        Schema::create('soporte_catalogo_modulos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('permiso_requerido')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('soporte_catalogo_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('soporte_catalogo_prioridades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->integer('tiempo_respuesta_horas');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('soporte_catalogo_estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('color')->default('#000000');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('soporte_base_conocimientos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('contenido');
            $table->foreignId('modulo_id')->constrained('soporte_catalogo_modulos')->onDelete('cascade');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('soporte_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('asignado_a_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('modulo_id')->constrained('soporte_catalogo_modulos')->onDelete('restrict');
            $table->foreignId('categoria_id')->constrained('soporte_catalogo_categorias')->onDelete('restrict');
            $table->foreignId('prioridad_sugerida_id')->nullable()->constrained('soporte_catalogo_prioridades')->onDelete('set null');
            $table->foreignId('prioridad_asignada_id')->nullable()->constrained('soporte_catalogo_prioridades')->onDelete('set null');
            $table->foreignId('estado_id')->constrained('soporte_catalogo_estados')->onDelete('restrict');
            $table->string('titulo');
            $table->text('descripcion');
            $table->dateTime('fecha_vencimiento_sla')->nullable();
            $table->dateTime('fecha_resolucion')->nullable();
            $table->timestamps();
        });

        Schema::create('soporte_ticket_interacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('soporte_tickets')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('mensaje');
            $table->boolean('es_nota_interna')->default(false);
            $table->timestamps();
        });

        Schema::create('soporte_ticket_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('soporte_tickets')->onDelete('cascade');
            $table->foreignId('interaccion_id')->nullable()->constrained('soporte_ticket_interacciones')->onDelete('cascade');
            $table->string('ruta_archivo');
            $table->string('nombre_archivo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soporte_ticket_adjuntos');
        Schema::dropIfExists('soporte_ticket_interacciones');
        Schema::dropIfExists('soporte_tickets');
        Schema::dropIfExists('soporte_base_conocimientos');
        Schema::dropIfExists('soporte_catalogo_estados');
        Schema::dropIfExists('soporte_catalogo_prioridades');
        Schema::dropIfExists('soporte_catalogo_categorias');
        Schema::dropIfExists('soporte_catalogo_modulos');
        Schema::dropIfExists('soporte_configuraciones');
    }
};
