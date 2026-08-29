<?php

use App\Models\ControlPedidos\PedidoBma;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Services\Reportes\PagosPedidos\RegistrarCierrePagoPedidoService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedido_bma_cierres_pago')) {
            return;
        }

        $pendientes = PedidoBma::query()
            ->whereNotNull('pago_validado_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('pedido_bma_cierres_pago')
                    ->whereColumn('pedido_bma_cierres_pago.pedido_bma_id', 'pedidos_bma.id');
            })
            ->count();

        if ($pendientes === 0) {
            return;
        }

        Artisan::call('reportes:backfill-cierres-pago');
    }

    public function down(): void
    {
        PedidoBmaCierrePago::query()
            ->where('origen', PedidoBmaCierrePago::ORIGEN_BACKFILL)
            ->delete();
    }
};
