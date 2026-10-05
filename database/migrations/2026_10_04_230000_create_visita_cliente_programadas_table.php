<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visita_cliente_programadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->date('fecha');
            $table->string('tipo_hora', 16);
            $table->time('hora_exacta')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('intencion', 32);
            $table->string('estado', 16)->default('programada');
            $table->foreignId('registrado_por_user_id')
                ->constrained('users', indexName: 'vcp_registrado_user_fk')
                ->restrictOnDelete();
            $table->timestamp('llegada_confirmada_at')->nullable();
            $table->foreignId('llegada_confirmada_por_user_id')
                ->nullable()
                ->constrained('users', indexName: 'vcp_llegada_user_fk')
                ->nullOnDelete();
            $table->string('idempotency_key', 120)->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'fecha', 'estado']);
            $table->index(['registrado_por_user_id', 'fecha']);
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visita_cliente_programadas');
    }
};
