<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('woocommerce_sync_details', function (Blueprint $table) {
            $table->id();
            // Vinculamos el detalle a la cabecera del Log
            $table->foreignId('sync_log_id')->constrained('woocommerce_sync_logs')->onDelete('cascade');
            
            $table->string('sku');
            $table->decimal('precio_anterior_normal', 10, 2)->nullable();
            $table->decimal('precio_nuevo_normal', 10, 2)->nullable();
            $table->decimal('precio_anterior_rebajado', 10, 2)->nullable();
            $table->decimal('precio_nuevo_rebajado', 10, 2)->nullable();
            
            $table->string('estado')->default('exito'); // 'exito' o 'error'
            $table->text('mensaje')->nullable(); // Para saber por qué rechazó WooCommerce si hay error
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('woocommerce_sync_details');
    }
};