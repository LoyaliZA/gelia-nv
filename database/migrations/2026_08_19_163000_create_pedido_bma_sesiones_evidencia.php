<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedido_bma_sesiones_evidencia')) {
            Schema::create('pedido_bma_sesiones_evidencia', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->string('codigo_publico', 32)->unique();
                $table->string('estado', 20);
                $table->timestamp('expira_en');
                $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reclamado_en')->nullable();
                $table->string('claim_ip', 45)->nullable();
                $table->text('claim_ua')->nullable();
                $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelado_en')->nullable();
                $table->json('snapshot_json')->nullable();
                $table->timestamps();

                $table->index(['pedido_bma_id', 'estado'], 'sesion_ev_pedido_estado_idx');
            });
        }

        if (! Schema::hasTable('pedido_bma_sesion_evidencia_fotos')) {
            Schema::create('pedido_bma_sesion_evidencia_fotos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sesion_id')->constrained('pedido_bma_sesiones_evidencia')->cascadeOnDelete();
                $table->string('objetivo_tipo', 20);
                $table->string('objetivo_uuid', 64);
                $table->unsignedInteger('indice_caja')->nullable();
                $table->string('ruta_archivo');
                $table->string('nombre_original')->nullable();
                $table->string('mime_type', 80)->nullable();
                $table->unsignedInteger('tamano_bytes')->nullable();
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('subido_en');
                $table->timestamps();

                $table->index(['sesion_id', 'objetivo_tipo', 'objetivo_uuid'], 'sesion_ev_fotos_obj_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_sesion_evidencia_fotos');
        Schema::dropIfExists('pedido_bma_sesiones_evidencia');
    }
};
