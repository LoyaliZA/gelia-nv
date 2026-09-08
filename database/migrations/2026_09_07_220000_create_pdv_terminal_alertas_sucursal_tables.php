<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_terminal_alertas_sucursal', function (Blueprint $table) {
            $table->id();
            $table->uuid('terminal_id');
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('estado', 24);
            $table->timestamp('activada_at');
            $table->timestamp('ultima_senal_at');
            $table->timestamp('liberada_at')->nullable();
            $table->timestamps();

            $table->unique('terminal_id', 'pdv_terminal_alertas_terminal_unique');
            $table->index(['sucursal_id', 'estado'], 'pdv_terminal_alertas_sucursal_estado_idx');
        });

        Schema::create('pdv_terminal_alertas_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designacion_id')->nullable()->constrained('pdv_terminal_alertas_sucursal')->nullOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 48);
            $table->json('contexto')->nullable();
            $table->timestamp('ocurrido_at');
            $table->timestamps();

            $table->index(['sucursal_id', 'ocurrido_at'], 'pdv_terminal_alertas_aud_sucursal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_terminal_alertas_auditoria');
        Schema::dropIfExists('pdv_terminal_alertas_sucursal');
    }
};
