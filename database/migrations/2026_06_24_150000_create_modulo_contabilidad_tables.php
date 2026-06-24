<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contabilidad_catalogo_estatus_pago', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->timestamps();
        });

        Schema::create('contabilidad_catalogo_tipos_transaccion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->timestamps();
        });

        Schema::create('contabilidad_catalogo_frecuencia_pago', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->timestamps();
        });

        Schema::create('contabilidad_plataformas_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('frecuencia_pago_id')
                ->constrained('contabilidad_catalogo_frecuencia_pago')
                ->restrictOnDelete();
            $table->date('ultimo_corte')->nullable();
            $table->unsignedInteger('dias_personalizados')->nullable();
            $table->decimal('tasa_comision_pct', 5, 2);
            $table->decimal('cuota_fija', 8, 2);
            $table->decimal('tasa_iva_pct', 5, 2)->default(16.00);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('contabilidad_lotes_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plataforma_pago_id')
                ->constrained('contabilidad_plataformas_pago')
                ->cascadeOnDelete();
            $table->date('fecha_corte_esperada');
            $table->date('fecha_deposito_real')->nullable()->index();
            $table->decimal('monto_ventas_total', 12, 2)->default(0);
            $table->decimal('comisiones_plataforma_total', 12, 2)->default(0);
            $table->decimal('monto_esperado_banco', 12, 2)->default(0);
            $table->decimal('monto_real_banco', 12, 2)->nullable();
            $table->string('estatus')->default('pendiente')->index();
            $table->string('factura_referencia')->nullable();
            $table->timestamps();
        });

        Schema::create('contabilidad_pedidos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_salida')->index();
            $table->string('numero_pedido')->unique();
            $table->string('cliente_nombre')->nullable();
            $table->foreignId('tipo_transaccion_id')
                ->constrained('contabilidad_catalogo_tipos_transaccion')
                ->restrictOnDelete();
            $table->foreignId('plataforma_pago_id')
                ->constrained('contabilidad_plataformas_pago')
                ->restrictOnDelete();
            $table->foreignId('lote_pago_id')
                ->nullable()
                ->constrained('contabilidad_lotes_pago')
                ->restrictOnDelete();
            $table->decimal('venta_total', 10, 2);
            $table->decimal('costo_envio', 8, 2)->default(0);
            $table->boolean('envio_pagado_cliente')->default(false);
            $table->decimal('comision_base', 8, 2)->default(0);
            $table->decimal('comision_iva', 8, 2)->default(0);
            $table->decimal('tasa_comision_pct', 5, 2);
            $table->decimal('cuota_fija', 8, 2);
            $table->decimal('comision_plataforma', 8, 2)->default(0);
            $table->decimal('utilidad_total', 10, 2)->default(0);
            $table->foreignId('estatus_pago_id')
                ->constrained('contabilidad_catalogo_estatus_pago')
                ->restrictOnDelete();
            $table->decimal('comision_transferencia', 10, 2)->default(0);
            $table->date('fecha_retiro')->nullable();
            $table->boolean('bloqueado')->default(false);
            $table->timestamps();

            $table->index(['fecha_salida', 'estatus_pago_id']);
            $table->index(['plataforma_pago_id', 'estatus_pago_id']);
        });

        Schema::create('contabilidad_pedido_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')
                ->constrained('contabilidad_pedidos')
                ->cascadeOnDelete();
            $table->string('sku');
            $table->unsignedInteger('piezas')->default(1);
            $table->string('nombre_producto');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contabilidad_pedido_lineas');
        Schema::dropIfExists('contabilidad_pedidos');
        Schema::dropIfExists('contabilidad_lotes_pago');
        Schema::dropIfExists('contabilidad_plataformas_pago');
        Schema::dropIfExists('contabilidad_catalogo_frecuencia_pago');
        Schema::dropIfExists('contabilidad_catalogo_tipos_transaccion');
        Schema::dropIfExists('contabilidad_catalogo_estatus_pago');
    }
};
