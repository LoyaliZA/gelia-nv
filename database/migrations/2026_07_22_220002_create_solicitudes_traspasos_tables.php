<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_traspasos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('vendedor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('almacen_origen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('catalogo_estado_solicitud_id')->constrained('catalogo_estados_solicitud');
            $table->foreignId('catalogo_horario_traspaso_id')->nullable()->constrained('catalogo_horarios_traspaso')->nullOnDelete();
            $table->date('fecha_entrega_estimada')->nullable();
            $table->unsignedInteger('total_piezas')->default(0);
            $table->string('folio_traspaso')->nullable();
            $table->string('evidencia_respuesta_path')->nullable();
            $table->text('motivo_respuesta')->nullable();
            $table->string('motivo_incorrecta')->nullable();
            $table->foreignId('respondida_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('respondida_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('solicitud_traspaso_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_traspaso_id')->constrained('solicitudes_traspasos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->string('sku');
            $table->string('descripcion');
            $table->unsignedInteger('piezas');
            $table->timestamps();
        });

        Schema::create('auditorias_solicitudes_traspasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_traspaso_id')->constrained('solicitudes_traspasos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('estado_anterior_id')->nullable()->constrained('catalogo_estados_solicitud')->nullOnDelete();
            $table->foreignId('estado_nuevo_id')->nullable()->constrained('catalogo_estados_solicitud')->nullOnDelete();
            $table->text('motivo_reporte')->nullable();
            $table->json('datos_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_solicitudes_traspasos');
        Schema::dropIfExists('solicitud_traspaso_productos');
        Schema::dropIfExists('solicitudes_traspasos');
    }
};
