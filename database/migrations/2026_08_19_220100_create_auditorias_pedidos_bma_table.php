<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias_pedidos_bma', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_bma_id');
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('accion', 20);
            $table->text('motivo');
            $table->string('fase_ciclo', 40)->nullable();
            $table->string('folio')->nullable();
            $table->string('folio_remision')->nullable();
            $table->unsignedBigInteger('estatus_id')->nullable();
            $table->json('datos_snapshot')->nullable();
            $table->timestamps();

            $table->index(['pedido_bma_id', 'accion']);
            $table->foreign('pedido_bma_id')
                ->references('id')
                ->on('pedidos_bma')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_pedidos_bma');
    }
};
