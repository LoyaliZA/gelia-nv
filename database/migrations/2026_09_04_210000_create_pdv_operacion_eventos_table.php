<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_operacion_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_dia_id')->constrained('pdv_sucursal_dias')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->string('tipo_evento', 64);
            $table->timestamp('ocurrido_at');
            $table->json('snapshot_json')->nullable();
            $table->string('idempotency_key', 128);
            $table->timestamps();

            $table->unique('idempotency_key', 'pdv_operacion_eventos_idempotency_unique');
            $table->index(['sucursal_id', 'tipo_evento'], 'pdv_operacion_eventos_sucursal_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_operacion_eventos');
    }
};
