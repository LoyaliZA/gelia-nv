<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_bma_bultos_empaque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->foreignId('empacado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('empacado_at')->nullable();
            $table->timestamps();

            $table->unique(['pedido_bma_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_bultos_empaque');
    }
};
