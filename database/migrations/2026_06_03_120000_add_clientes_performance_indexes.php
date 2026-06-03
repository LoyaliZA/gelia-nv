<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->index('vendedor_id', 'clientes_vendedor_id_index');
            $table->index('vendedor_original_id', 'clientes_vendedor_original_id_index');
            $table->index('lista_bloqueada', 'clientes_lista_bloqueada_index');
            $table->index('nombre', 'clientes_nombre_index');
        });

        Schema::table('historial_montos_clientes', function (Blueprint $table) {
            $table->index(['cliente_id', 'created_at'], 'historial_montos_cliente_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('clientes_vendedor_id_index');
            $table->dropIndex('clientes_vendedor_original_id_index');
            $table->dropIndex('clientes_lista_bloqueada_index');
            $table->dropIndex('clientes_nombre_index');
        });

        Schema::table('historial_montos_clientes', function (Blueprint $table) {
            $table->dropIndex('historial_montos_cliente_created_index');
        });
    }
};
