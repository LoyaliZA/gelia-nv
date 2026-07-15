<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $labels = [
            'BORRADOR' => 'Borrador',
            'PENDIENTE_AUXILIAR' => 'Pendiente Auxiliar',
            'EN_CEDIS' => 'En CEDIS',
            'RECHAZADO_VENDEDORA' => 'Rechazado',
            'INCIDENCIA_CEDIS' => 'Incidencia CEDIS',
            'EN_RUTA' => 'En ruta',
            'PENDIENTE_DE_GUIA' => 'Pendiente de guía',
            'PENDIENTE_DE_ENVIO' => 'Pendiente de envío',
            'ENTREGADO' => 'Entregado',
            'ENVIADO' => 'Enviado',
        ];

        foreach ($labels as $fase => $nombre) {
            DB::table('catalogo_estatus_pedidos')
                ->where('fase_ciclo', $fase)
                ->update(['nombre_visual' => $nombre]);
        }
    }

    public function down(): void
    {
        $revert = [
            'PENDIENTE_AUXILIAR' => 'AZUL ①',
            'EN_CEDIS' => 'AMARILLO',
            'RECHAZADO_VENDEDORA' => 'NARANJA',
            'INCIDENCIA_CEDIS' => 'ROJO',
            'EN_RUTA' => 'VERDE',
        ];

        foreach ($revert as $fase => $nombre) {
            DB::table('catalogo_estatus_pedidos')
                ->where('fase_ciclo', $fase)
                ->update(['nombre_visual' => $nombre]);
        }
    }
};
