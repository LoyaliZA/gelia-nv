<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $motivos = [
            ['codigo' => 'pago_de_mas', 'nombre' => 'Cliente depositó o transfirió de más', 'categoria' => 'diferencias_pago', 'requiere_detalle' => false, 'orden' => 10],
            ['codigo' => 'pago_duplicado', 'nombre' => 'Pago duplicado', 'categoria' => 'diferencias_pago', 'requiere_detalle' => false, 'orden' => 20],
            ['codigo' => 'deposito_fuera_horario', 'nombre' => 'Depósito fuera de horario', 'categoria' => 'diferencias_pago', 'requiere_detalle' => false, 'orden' => 30],
            ['codigo' => 'pago_reflejado_posterior', 'nombre' => 'Pago reflejado posteriormente', 'categoria' => 'diferencias_pago', 'requiere_detalle' => false, 'orden' => 40],
            ['codigo' => 'pago_pedido_anterior', 'nombre' => 'Pago correspondiente a un pedido anterior', 'categoria' => 'diferencias_pago', 'requiere_detalle' => false, 'orden' => 50],
            ['codigo' => 'producto_no_disponible', 'nombre' => 'Producto no disponible', 'categoria' => 'ajustes_mercancia', 'requiere_detalle' => false, 'orden' => 60],
            ['codigo' => 'producto_eliminado', 'nombre' => 'Producto eliminado', 'categoria' => 'ajustes_mercancia', 'requiere_detalle' => false, 'orden' => 70],
            ['codigo' => 'producto_cancelado', 'nombre' => 'Producto cancelado', 'categoria' => 'ajustes_mercancia', 'requiere_detalle' => false, 'orden' => 80],
            ['codigo' => 'pieza_cobrada_demas', 'nombre' => 'Pieza cobrada de más', 'categoria' => 'ajustes_mercancia', 'requiere_detalle' => false, 'orden' => 90],
            ['codigo' => 'cambio_precio', 'nombre' => 'Cambio de precio', 'categoria' => 'ajustes_mercancia', 'requiere_detalle' => false, 'orden' => 100],
            ['codigo' => 'cambio_escalonamiento', 'nombre' => 'Cambio de escalonamiento', 'categoria' => 'ajustes_mercancia', 'requiere_detalle' => false, 'orden' => 110],
            ['codigo' => 'sobrante_envio', 'nombre' => 'Sobrante del envío', 'categoria' => 'ajustes_envio', 'requiere_detalle' => false, 'orden' => 120],
            ['codigo' => 'cambio_paqueteria', 'nombre' => 'Cambio de paquetería', 'categoria' => 'ajustes_envio', 'requiere_detalle' => false, 'orden' => 130],
            ['codigo' => 'guia_propia', 'nombre' => 'Cliente utilizó guía propia', 'categoria' => 'ajustes_envio', 'requiere_detalle' => false, 'orden' => 140],
            ['codigo' => 'convenio_paqueteria', 'nombre' => 'Cliente tiene convenio con la paquetería', 'categoria' => 'ajustes_envio', 'requiere_detalle' => false, 'orden' => 150],
            ['codigo' => 'envio_no_utilizado', 'nombre' => 'Envío no utilizado', 'categoria' => 'ajustes_envio', 'requiere_detalle' => false, 'orden' => 160],
            ['codigo' => 'envio_a_resguardo', 'nombre' => 'Cambio del envío a resguardo', 'categoria' => 'ajustes_envio', 'requiere_detalle' => false, 'orden' => 170],
            ['codigo' => 'ajuste_seguro', 'nombre' => 'Ajuste del seguro', 'categoria' => 'ajustes_envio', 'requiere_detalle' => false, 'orden' => 180],
            ['codigo' => 'error_remision', 'nombre' => 'Error en la remisión', 'categoria' => 'errores', 'requiere_detalle' => true, 'orden' => 190],
            ['codigo' => 'error_gelia', 'nombre' => 'Error en GELIA', 'categoria' => 'errores', 'requiere_detalle' => true, 'orden' => 200],
            ['codigo' => 'error_captura', 'nombre' => 'Error de captura', 'categoria' => 'errores', 'requiere_detalle' => true, 'orden' => 210],
            ['codigo' => 'otro', 'nombre' => 'Otro motivo', 'categoria' => 'errores', 'requiere_detalle' => true, 'orden' => 220],
            ['codigo' => 'migracion_historica', 'nombre' => 'Migración histórica', 'categoria' => 'sistema', 'requiere_detalle' => false, 'orden' => 900],
            ['codigo' => 'ajuste_admin', 'nombre' => 'Ajuste administrativo', 'categoria' => 'sistema', 'requiere_detalle' => true, 'orden' => 910],
        ];

        foreach ($motivos as $m) {
            DB::table('saf_motivos')->updateOrInsert(
                ['codigo' => $m['codigo']],
                array_merge($m, [
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('saf_motivos')->whereIn('codigo', [
            'pago_de_mas', 'pago_duplicado', 'deposito_fuera_horario', 'pago_reflejado_posterior',
            'pago_pedido_anterior', 'producto_no_disponible', 'producto_eliminado', 'producto_cancelado',
            'pieza_cobrada_demas', 'cambio_precio', 'cambio_escalonamiento', 'sobrante_envio',
            'cambio_paqueteria', 'guia_propia', 'convenio_paqueteria', 'envio_no_utilizado',
            'envio_a_resguardo', 'ajuste_seguro', 'error_remision', 'error_gelia', 'error_captura',
            'otro', 'migracion_historica', 'ajuste_admin',
        ])->delete();
    }
};
