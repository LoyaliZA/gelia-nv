<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $existe = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PESAJE_RESPONDIDO')
            ->exists();

        if (! $existe) {
            DB::table('catalogo_estatus_pedidos')->insert([
                'codigo_interno' => 'PESAJE_RESPONDIDO',
                'nombre_visual' => 'Pesaje respondido',
                'color_hex' => '#FB923C',
                'fase_ciclo' => 'PESAJE_RESPONDIDO',
                'orden' => 13,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $labels = [
            'PESAJE_PENDIENTE' => 'Pesaje pendiente',
            'PESAJE_RESPONDIDO' => 'Pesaje respondido',
            'PENDIENTE_AUXILIAR' => 'Pendiente de auditoría',
            'EN_CEDIS' => 'Pendiente de empaque',
            'RECHAZADO_VENDEDORA' => 'Rechazado o devuelto para corrección',
            'PENDIENTE_DE_GUIA' => 'Pendiente de guía',
            'PENDIENTE_GUIA_CLIENTE' => 'Pendiente de guía del cliente',
            'PENDIENTE_DE_ENVIO' => 'Pendiente de recolección o envío',
            'ENVIADO' => 'Enviado',
        ];

        foreach ($labels as $fase => $nombre) {
            DB::table('catalogo_estatus_pedidos')
                ->where('fase_ciclo', $fase)
                ->update(['nombre_visual' => $nombre, 'updated_at' => $now]);
        }

        $respondidoId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PESAJE_RESPONDIDO')
            ->value('id');
        $pendienteId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PESAJE_PENDIENTE')
            ->value('id');

        if ($respondidoId && $pendienteId) {
            DB::table('pedidos_bma')
                ->where('catalogo_estatus_pedido_id', $pendienteId)
                ->whereNotNull('pesaje_respondido_at')
                ->update(['catalogo_estatus_pedido_id' => $respondidoId]);
        }
    }

    public function down(): void
    {
        $respondidoId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PESAJE_RESPONDIDO')
            ->value('id');
        $pendienteId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PESAJE_PENDIENTE')
            ->value('id');

        if ($respondidoId && $pendienteId) {
            DB::table('pedidos_bma')
                ->where('catalogo_estatus_pedido_id', $respondidoId)
                ->update(['catalogo_estatus_pedido_id' => $pendienteId]);
        }

        DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'PESAJE_RESPONDIDO')
            ->delete();

        $revert = [
            'PENDIENTE_AUXILIAR' => 'Pendiente Auxiliar',
            'EN_CEDIS' => 'En CEDIS',
            'RECHAZADO_VENDEDORA' => 'Rechazado',
            'PENDIENTE_DE_ENVIO' => 'Pendiente de envío',
        ];

        foreach ($revert as $fase => $nombre) {
            DB::table('catalogo_estatus_pedidos')
                ->where('fase_ciclo', $fase)
                ->update(['nombre_visual' => $nombre]);
        }
    }
};
