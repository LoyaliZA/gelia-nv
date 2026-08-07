<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->boolean('cliente_proporciona_guia')->default(false)->after('aplica_seguro');
        });

        $now = now();
        $existe = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PENDIENTE_GUIA_CLIENTE')
            ->exists();

        if (! $existe) {
            DB::table('catalogo_estatus_pedidos')->insert([
                'codigo_interno' => 'PENDIENTE_GUIA_CLIENTE',
                'nombre_visual' => 'Pendiente de guía del cliente',
                'color_hex' => '#C026D3',
                'fase_ciclo' => 'PENDIENTE_GUIA_CLIENTE',
                'orden' => 11,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $faseId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PENDIENTE_GUIA_CLIENTE')
            ->value('id');

        if ($faseId) {
            $pendienteGuiaId = DB::table('catalogo_estatus_pedidos')
                ->where('fase_ciclo', 'PENDIENTE_DE_GUIA')
                ->value('id');

            if ($pendienteGuiaId) {
                DB::table('pedidos_bma')
                    ->where('catalogo_estatus_pedido_id', $faseId)
                    ->update(['catalogo_estatus_pedido_id' => $pendienteGuiaId]);
            }

            DB::table('catalogo_estatus_pedidos')
                ->where('fase_ciclo', 'PENDIENTE_GUIA_CLIENTE')
                ->delete();
        }

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropColumn('cliente_proporciona_guia');
        });
    }
};
