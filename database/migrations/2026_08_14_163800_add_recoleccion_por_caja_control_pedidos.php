<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_cajas', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_bma_cajas', 'estatus_recoleccion')) {
                $table->string('estatus_recoleccion', 20)->default('pendiente')->after('catalogo_tipo_guia_id');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'recolectada_at')) {
                $table->timestamp('recolectada_at')->nullable()->after('estatus_recoleccion');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'recolectada_por_id')) {
                $table->foreignId('recolectada_por_id')->nullable()->after('recolectada_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'numero_rastreo')) {
                $table->string('numero_rastreo', 100)->nullable()->after('recolectada_por_id');
            }
        });

        $this->backfillCajasYaEnviadas();
    }

    public function down(): void
    {
        Schema::table('pedido_bma_cajas', function (Blueprint $table) {
            if (Schema::hasColumn('pedido_bma_cajas', 'recolectada_por_id')) {
                $table->dropConstrainedForeignId('recolectada_por_id');
            }
            $drop = array_values(array_filter([
                Schema::hasColumn('pedido_bma_cajas', 'estatus_recoleccion') ? 'estatus_recoleccion' : null,
                Schema::hasColumn('pedido_bma_cajas', 'recolectada_at') ? 'recolectada_at' : null,
                Schema::hasColumn('pedido_bma_cajas', 'numero_rastreo') ? 'numero_rastreo' : null,
            ]));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    private function backfillCajasYaEnviadas(): void
    {
        if (! Schema::hasTable('catalogo_estatus_pedido') || ! Schema::hasTable('pedidos_bma')) {
            return;
        }

        $fasesEnviadas = ['ENVIADO', 'ENTREGADO', 'CANCELADO'];
        $estatusIds = DB::table('catalogo_estatus_pedido')
            ->whereIn('fase_ciclo', $fasesEnviadas)
            ->pluck('id');

        if ($estatusIds->isEmpty()) {
            return;
        }

        $pedidoIds = DB::table('pedidos_bma')
            ->whereIn('catalogo_estatus_pedido_id', $estatusIds)
            ->pluck('id');

        if ($pedidoIds->isEmpty()) {
            return;
        }

        DB::table('pedido_bma_cajas')
            ->whereIn('pedido_bma_id', $pedidoIds)
            ->where('estatus_recoleccion', 'pendiente')
            ->update([
                'estatus_recoleccion' => 'recolectada',
                'recolectada_at' => now(),
            ]);
    }
};
