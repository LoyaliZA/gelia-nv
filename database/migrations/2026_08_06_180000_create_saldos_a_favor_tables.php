<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saf_motivos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 64)->unique();
            $table->string('nombre');
            $table->string('categoria', 64)->nullable();
            $table->boolean('requiere_detalle')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('saf_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('moneda', 3)->default('MXN');
            $table->timestamps();
            $table->unique(['cliente_id', 'moneda']);
        });

        Schema::create('saf_creditos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 32)->unique();
            $table->foreignId('saf_cuenta_id')->constrained('saf_cuentas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('canal_origen', 64)->nullable();
            $table->string('sucursal', 128)->nullable();
            $table->string('departamento', 128)->nullable();
            $table->unsignedBigInteger('pedido_bma_id')->nullable()->index();
            $table->string('documento_origen', 128)->nullable();
            $table->decimal('monto_original', 14, 2);
            $table->decimal('monto_aplicado', 14, 2)->default(0);
            $table->decimal('monto_reservado', 14, 2)->default(0);
            $table->decimal('monto_disponible', 14, 2);
            $table->timestamp('fecha_generacion');
            $table->date('fecha_vencimiento');
            $table->foreignId('saf_motivo_id')->nullable()->constrained('saf_motivos')->nullOnDelete();
            $table->text('detalle_motivo')->nullable();
            $table->foreignId('generado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado_financiero', 32)->default('disponible');
            $table->string('estado_revision', 32)->default('pendiente');
            $table->text('observaciones_revision')->nullable();
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'estado_financiero']);
            $table->index(['fecha_vencimiento', 'estado_financiero']);
            $table->index('estado_revision');
        });

        Schema::create('saf_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saf_credito_id')->constrained('saf_creditos')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('tipo', 32);
            $table->decimal('monto', 14, 2);
            $table->decimal('saldo_anterior', 14, 2);
            $table->decimal('saldo_posterior', 14, 2);
            $table->unsignedBigInteger('pedido_bma_id')->nullable()->index();
            $table->unsignedBigInteger('saf_comprobante_caja_id')->nullable()->index();
            $table->unsignedBigInteger('saf_pedido_aplicacion_id')->nullable()->index();
            $table->string('referencia_externa', 128)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('saf_motivo_id')->nullable()->constrained('saf_motivos')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['saf_credito_id', 'tipo']);
            $table->index(['cliente_id', 'created_at']);
        });

        Schema::create('saf_evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saf_credito_id')->nullable()->constrained('saf_creditos')->cascadeOnDelete();
            $table->foreignId('saf_movimiento_id')->nullable()->constrained('saf_movimientos')->cascadeOnDelete();
            $table->string('ruta_archivo');
            $table->string('nombre_original')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedInteger('tamano_bytes')->nullable();
            $table->foreignId('subido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('saf_pedido_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_bma_id')->index();
            $table->foreignId('saf_credito_id')->constrained('saf_creditos')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);
            $table->string('estado', 32)->default('reservado');
            $table->foreignId('reservado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aplicado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reservado_at')->nullable();
            $table->timestamp('aplicado_at')->nullable();
            $table->timestamp('liberado_at')->nullable();
            $table->timestamps();

            $table->index(['pedido_bma_id', 'estado']);
        });

        Schema::create('pedido_bma_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_bma_id')->index();
            $table->unsignedSmallInteger('numero_exhibicion')->default(1);
            $table->decimal('monto', 14, 2);
            $table->foreignId('catalogo_banco_id')->nullable()->constrained('catalogo_bancos')->nullOnDelete();
            $table->string('forma_pago', 64)->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->string('referencia', 128)->nullable();
            $table->string('ruta_archivo')->nullable();
            $table->string('nombre_original')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->unsignedInteger('tamano_bytes')->nullable();
            $table->foreignId('capturado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado_revision', 32)->default('pendiente');
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['pedido_bma_id', 'numero_exhibicion']);
        });

        Schema::create('saf_comprobantes_caja', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 40)->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('saf_cuenta_id')->constrained('saf_cuentas')->cascadeOnDelete();
            $table->string('referencia_venta', 128)->nullable();
            $table->string('sucursal', 128)->nullable();
            $table->string('caja', 64)->nullable();
            $table->decimal('saldo_anterior', 14, 2)->default(0);
            $table->decimal('monto_aplicado', 14, 2)->default(0);
            $table->decimal('saldo_restante', 14, 2)->default(0);
            $table->json('creditos_detalle')->nullable();
            $table->string('estado', 48)->default('generado');
            $table->string('perfil_impresion', 16)->default('80mm');
            $table->foreignId('generado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('firmado_at')->nullable();
            $table->timestamp('aplicado_at')->nullable();
            $table->timestamp('revisado_at')->nullable();
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ruta_evidencia_firmada')->nullable();
            $table->boolean('es_reimpresion')->default(false);
            $table->timestamps();

            $table->index(['cliente_id', 'estado']);
        });

        Schema::create('saf_comprobante_reimpresiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saf_comprobante_caja_id')->constrained('saf_comprobantes_caja')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('perfil_impresion', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('saf_impresion_preferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('terminal_key', 64)->default('default');
            $table->string('perfil', 16)->default('80mm');
            $table->unsignedTinyInteger('copias')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'terminal_key']);
        });

        Schema::create('saf_incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('saf_credito_id')->nullable()->constrained('saf_creditos')->nullOnDelete();
            $table->unsignedBigInteger('pedido_bma_id')->nullable()->index();
            $table->string('tipo', 64);
            $table->text('descripcion');
            $table->string('estado', 32)->default('abierta');
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelto_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saf_incidencias');
        Schema::dropIfExists('saf_impresion_preferencias');
        Schema::dropIfExists('saf_comprobante_reimpresiones');
        Schema::dropIfExists('saf_comprobantes_caja');
        Schema::dropIfExists('pedido_bma_pagos');
        Schema::dropIfExists('saf_pedido_aplicaciones');
        Schema::dropIfExists('saf_evidencias');
        Schema::dropIfExists('saf_movimientos');
        Schema::dropIfExists('saf_creditos');
        Schema::dropIfExists('saf_cuentas');
        Schema::dropIfExists('saf_motivos');
    }
};
