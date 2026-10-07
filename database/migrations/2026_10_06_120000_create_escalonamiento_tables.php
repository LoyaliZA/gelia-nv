<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalonamiento_reglas_versiones', function (Blueprint $table) {
            $table->id();
            $table->json('snapshot');
            $table->timestamps();
        });

        Schema::create('escalonamiento_periodos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('zona_horaria')->default('America/Mexico_City');
            $table->string('estado')->default('abierto');
            $table->timestamp('fecha_corte')->nullable();
            $table->foreignId('escalonamiento_regla_version_id')
                ->nullable()
                ->constrained('escalonamiento_reglas_versiones')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['anio', 'mes']);
        });

        Schema::create('documentos_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalonamiento_periodo_id')->constrained('escalonamiento_periodos');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('tipo');
            $table->string('folio');
            $table->string('serie')->nullable();
            $table->string('sucursal')->nullable();
            $table->string('moneda', 8)->default('MXN');
            $table->decimal('total', 14, 2);
            $table->string('estado')->default('activo');
            $table->date('fecha_emision');
            $table->string('origen')->default('manual');
            $table->string('clave_documento');
            $table->timestamps();

            $table->unique('clave_documento');
            $table->index(['escalonamiento_periodo_id', 'cliente_id'], 'esc_doc_periodo_cliente_idx');
        });

        Schema::create('escalonamiento_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalonamiento_periodo_id')->constrained('escalonamiento_periodos');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('documento_venta_id')->unique()->constrained('documentos_venta');
            $table->decimal('efecto', 14, 2);
            $table->string('operacion');
            $table->timestamps();

            $table->index(['escalonamiento_periodo_id', 'cliente_id'], 'esc_mov_periodo_cliente_idx');
        });

        Schema::create('escalonamiento_resumenes_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalonamiento_periodo_id')
                ->constrained(table: 'escalonamiento_periodos', indexName: 'esc_resumen_periodo_fk');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->decimal('acumulado', 14, 2)->default(0);
            $table->foreignId('lista_base_id')->nullable()->constrained('catalogo_listas_descuento');
            $table->foreignId('clasificacion_mes_id')->nullable()->constrained('catalogo_listas_descuento');
            $table->foreignId('lista_vigente_id')->nullable()->constrained('catalogo_listas_descuento');
            $table->timestamps();

            $table->unique(['escalonamiento_periodo_id', 'cliente_id'], 'escalonamiento_resumen_periodo_cliente_unico');
        });

        Schema::create('escalonamiento_incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escalonamiento_periodo_id')->nullable()->constrained('escalonamiento_periodos');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('documento_venta_id')->nullable()->constrained('documentos_venta');
            $table->string('gravedad')->default('aviso');
            $table->text('motivo');
            $table->string('estado')->default('abierta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalonamiento_incidencias');
        Schema::dropIfExists('escalonamiento_resumenes_cliente');
        Schema::dropIfExists('escalonamiento_movimientos');
        Schema::dropIfExists('documentos_venta');
        Schema::dropIfExists('escalonamiento_periodos');
        Schema::dropIfExists('escalonamiento_reglas_versiones');
    }
};
