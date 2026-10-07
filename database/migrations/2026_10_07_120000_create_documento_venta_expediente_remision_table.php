<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_venta_expediente_remision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_venta_id')
                ->unique()
                ->constrained('documentos_venta')
                ->cascadeOnDelete();
            $table->string('folio', 50);
            $table->dateTime('fecha')->nullable();
            $table->string('cliente', 255)->nullable();
            $table->string('sucursal', 100)->nullable();
            $table->char('moneda', 3)->default('MXN');
            $table->decimal('subtotal', 14, 2)->nullable();
            $table->decimal('descuento', 14, 2)->nullable();
            $table->decimal('iva', 14, 2)->nullable();
            $table->decimal('importe_ieps', 14, 2)->nullable();
            $table->decimal('retencion_iva', 14, 2)->nullable();
            $table->decimal('retencion_isr', 14, 2)->nullable();
            $table->decimal('retencion_ieps', 14, 2)->nullable();
            $table->decimal('total', 14, 2)->nullable();
            $table->decimal('utilidad', 14, 2)->nullable();
            $table->boolean('facturada')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('condicion_pago', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('vencimiento', 50)->nullable();
            $table->string('status_pago', 50)->nullable();
            $table->string('metodo_pago', 100)->nullable();
            $table->string('origen', 100)->nullable();
            $table->string('almacen', 150)->nullable();
            $table->string('vendedor', 150)->nullable();
            $table->string('plataforma', 100)->nullable();
            $table->string('numero_venta_plataforma', 100)->nullable();
            $table->timestamps();

            $table->index('fecha');
            $table->index('sucursal');
            $table->index('origen');
            $table->index('plataforma');
            $table->index('vendedor');
            $table->index('status_pago');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_venta_expediente_remision');
    }
};
