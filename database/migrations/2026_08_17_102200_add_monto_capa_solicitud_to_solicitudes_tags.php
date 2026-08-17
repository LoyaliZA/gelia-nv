<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->decimal('monto_aplicado_al_cliente', 12, 2)->default(0)->after('monto_cotizado');
            $table->boolean('cubierto_por_carga_masiva')->default(false)->after('monto_aplicado_al_cliente');
        });

        $this->backfillCapasPendientes();
    }

    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropColumn(['monto_aplicado_al_cliente', 'cubierto_por_carga_masiva']);
        });
    }

    private function backfillCapasPendientes(): void
    {
        if (! Schema::hasTable('historial_montos_clientes')) {
            return;
        }

        $pendientes = DB::table('solicitudes_tags')
            ->where('pago_confirmado', false)
            ->whereNull('deleted_at')
            ->whereNotNull('cliente_id')
            ->get(['id', 'cliente_id']);

        foreach ($pendientes as $solicitud) {
            $aplicacion = DB::table('historial_montos_clientes')
                ->where('solicitud_id', $solicitud->id)
                ->whereIn('origen', ['solicitud_aprobacion', 'solicitud_pago'])
                ->where('diferencia_aplicada', '>', 0)
                ->orderByDesc('id')
                ->first();

            if (! $aplicacion) {
                continue;
            }

            $cargaPosterior = DB::table('historial_montos_clientes')
                ->where('cliente_id', $solicitud->cliente_id)
                ->where('origen', 'carga_masiva')
                ->where('id', '>', $aplicacion->id)
                ->exists();

            if ($cargaPosterior) {
                DB::table('solicitudes_tags')->where('id', $solicitud->id)->update([
                    'monto_aplicado_al_cliente' => 0,
                    'cubierto_por_carga_masiva' => true,
                ]);
                continue;
            }

            $capa = (float) ($aplicacion->monto_operacion ?? $aplicacion->diferencia_aplicada);
            if ($capa <= 0) {
                continue;
            }

            DB::table('solicitudes_tags')->where('id', $solicitud->id)->update([
                'monto_aplicado_al_cliente' => $capa,
                'cubierto_por_carga_masiva' => false,
            ]);
        }
    }
};
