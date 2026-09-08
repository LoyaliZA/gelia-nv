<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_operacion_gestion_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->string('accion', 64);
            $table->string('estado_anterior', 64);
            $table->string('estado_nuevo', 64);
            $table->timestamp('registrado_at');
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();

            $table->index(
                ['sucursal_id', 'registrado_at'],
                'pdv_operacion_gestion_auditoria_sucursal_fecha_idx',
            );
            $table->index(
                ['user_id', 'registrado_at'],
                'pdv_operacion_gestion_auditoria_user_fecha_idx',
            );
            $table->unique(
                'idempotency_key',
                'pdv_operacion_gestion_auditoria_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_operacion_gestion_auditoria');
    }
};
