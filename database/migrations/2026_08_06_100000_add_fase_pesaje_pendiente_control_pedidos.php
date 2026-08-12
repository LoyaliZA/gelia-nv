<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $existe = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PESAJE_PENDIENTE')
            ->exists();

        if (! $existe) {
            DB::table('catalogo_estatus_pedidos')->insert([
                'codigo_interno' => 'PESAJE_PENDIENTE',
                'nombre_visual' => 'Pesaje pendiente',
                'color_hex' => '#F97316',
                'fase_ciclo' => 'PESAJE_PENDIENTE',
                'orden' => 12,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $faseId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PESAJE_PENDIENTE')
            ->value('id');

        if (! $faseId) {
            return;
        }

        $borradorId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'BORRADOR')
            ->value('id');

        if ($borradorId) {
            DB::table('pedidos_bma')
                ->where('catalogo_estatus_pedido_id', $faseId)
                ->update(['catalogo_estatus_pedido_id' => $borradorId]);
        }

        DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PESAJE_PENDIENTE')
            ->delete();
    }
};
