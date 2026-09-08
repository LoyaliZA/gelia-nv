<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_pantalla_sala_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('estado', 24);
            $table->timestamp('expira_en')->nullable();
            $table->timestamp('revocado_en')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->timestamps();

            $table->index(['sucursal_id', 'estado'], 'pdv_pantalla_sala_sucursal_estado_idx');
        });

        Schema::create('pdv_pantalla_sala_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_id')->nullable()->constrained('pdv_pantalla_sala_tokens')->nullOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 48);
            $table->json('contexto')->nullable();
            $table->timestamp('ocurrido_at');
            $table->timestamps();

            $table->index(['sucursal_id', 'ocurrido_at'], 'pdv_pantalla_sala_aud_sucursal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_pantalla_sala_auditoria');
        Schema::dropIfExists('pdv_pantalla_sala_tokens');
    }
};
