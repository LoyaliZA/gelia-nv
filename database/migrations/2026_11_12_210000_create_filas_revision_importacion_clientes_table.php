<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filas_revision_importacion_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_cliente_id')
                ->constrained('importaciones_clientes', 'id', 'filas_rev_cli_importacion_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('numero_fila');
            $table->string('numero_cliente')->nullable();
            $table->text('nombre')->nullable();
            $table->string('categoria', 32);
            $table->text('motivo')->nullable();
            $table->decimal('monto_anterior', 14, 2)->nullable();
            $table->decimal('monto_nuevo', 14, 2)->nullable();
            $table->boolean('es_bloqueo')->default(false);
            $table->boolean('es_aviso')->default(false);
            $table->index(['importacion_cliente_id', 'categoria'], 'filas_rev_cli_cat_idx');
            $table->index(['importacion_cliente_id', 'numero_cliente'], 'filas_rev_cli_num_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filas_revision_importacion_clientes');
    }
};
