<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pedido_bma_errores')) {
            return;
        }

        Schema::create('pedido_bma_errores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->json('campos');
            $table->text('descripcion')->nullable();
            $table->foreignId('reportado_por_id')->constrained('users')->restrictOnDelete();
            $table->string('responsable_dueno', 32);
            $table->foreignId('responsable_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reportado_at');
            $table->foreignId('corregido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corregido_at')->nullable();
            $table->text('correccion_realizada')->nullable();
            $table->string('estatus', 20)->default('abierto');
            $table->timestamps();

            $table->index(['pedido_bma_id', 'estatus']);
            $table->index(['pedido_bma_id', 'responsable_dueno', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_errores');
    }
};
