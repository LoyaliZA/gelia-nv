<?php

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correctiva idempotente: backfill de recolección por caja.
 * La migración 2026_08_14_163800 consultaba catalogo_estatus_pedido (singular) y no aplicó.
 * No editar esa migración ya aplicada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedido_bma_cajas') || ! Schema::hasColumn('pedido_bma_cajas', 'estatus_recoleccion')) {
            return;
        }
        if (! Schema::hasTable('catalogo_estatus_pedidos')) {
            logger()->warning('fase2_correctiva_recoleccion: falta catalogo_estatus_pedidos');

            return;
        }

        $fasesTerminadas = [
            CatalogoEstatusPedido::FASE_ENVIADO,
            CatalogoEstatusPedido::FASE_ENTREGADO,
            CatalogoEstatusPedido::FASE_CANCELADO,
        ];

        $estatusIds = DB::table('catalogo_estatus_pedidos')
            ->whereIn('fase_ciclo', $fasesTerminadas)
            ->pluck('id')
            ->all();

        if ($estatusIds === []) {
            logger()->warning('fase2_correctiva_recoleccion: sin estatus ENVIADO/ENTREGADO/CANCELADO');

            return;
        }

        $marcadas = 0;
        $ambiguos = 0;
        $omitidos = 0;

        DB::table('pedidos_bma')
            ->whereIn('catalogo_estatus_pedido_id', $estatusIds)
            ->orderBy('id')
            ->chunkById(100, function ($pedidos) use (&$marcadas, &$ambiguos, &$omitidos) {
                foreach ($pedidos as $pedido) {
                    $cajas = DB::table('pedido_bma_cajas')
                        ->where('pedido_bma_id', $pedido->id)
                        ->get();
                    if ($cajas->isEmpty()) {
                        $omitidos++;

                        continue;
                    }

                    $pendientes = $cajas->filter(function ($c) {
                        $est = $c->estatus_recoleccion ?: 'pendiente';

                        return $est === 'pendiente';
                    });
                    if ($pendientes->isEmpty()) {
                        $omitidos++;

                        continue;
                    }

                    // Inequívoco: pedido ya en fase terminal y todas las cajas pendientes
                    // (ninguna recolectada parcial). Si hay mezcla, es ambiguo.
                    $recolectadas = $cajas->filter(fn ($c) => ($c->estatus_recoleccion ?: '') === 'recolectada');
                    if ($recolectadas->isNotEmpty() && $pendientes->isNotEmpty()) {
                        $ambiguos++;
                        logger()->info('fase2_correctiva_recoleccion ambiguo', [
                            'pedido_bma_id' => $pedido->id,
                            'pendientes' => $pendientes->count(),
                            'recolectadas' => $recolectadas->count(),
                        ]);

                        continue;
                    }

                    $ids = $pendientes->pluck('id')->all();
                    DB::table('pedido_bma_cajas')
                        ->whereIn('id', $ids)
                        ->update([
                            'estatus_recoleccion' => 'recolectada',
                            'recolectada_at' => $pedido->enviado_at ?? $pedido->updated_at ?? now(),
                        ]);
                    $marcadas += count($ids);
                }
            });

        logger()->info('fase2_correctiva_recoleccion resumen', [
            'marcadas' => $marcadas,
            'ambiguos' => $ambiguos,
            'omitidos' => $omitidos,
        ]);
    }

    public function down(): void
    {
        // No reversible de forma segura: no se revierten marcas de recolección.
    }
};
