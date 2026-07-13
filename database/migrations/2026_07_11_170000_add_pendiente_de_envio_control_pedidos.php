<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $existe = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PENDIENTE_DE_ENVIO')
            ->exists();

        if (!$existe) {
            DB::table('catalogo_estatus_pedidos')->insert([
                'codigo_interno' => 'PENDIENTE_ENVIO',
                'nombre_visual' => 'Pendiente de envío',
                'color_hex' => '#0EA5E9',
                'fase_ciclo' => 'PENDIENTE_DE_ENVIO',
                'orden' => 10,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $pendienteEnvioId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PENDIENTE_DE_ENVIO')
            ->value('id');

        if (!$pendienteEnvioId) {
            return;
        }

        $enviadoId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'ENVIADO')
            ->value('id');

        if ($enviadoId) {
            DB::table('pedidos_bma')
                ->where('catalogo_estatus_pedido_id', $pendienteEnvioId)
                ->update(['catalogo_estatus_pedido_id' => $enviadoId]);
        }

        DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PENDIENTE_DE_ENVIO')
            ->delete();
    }
};
