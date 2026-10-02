<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTraspasoDiscrepancia;
use App\Models\SolicitudTraspaso;
use App\Models\SolicitudTraspasoRevisionProducto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Registra discrepancias por SKU/cantidad entre lo enviado por Tienda y lo revisado en CEDIS.
 * No ajusta inventario ni fabrica unidades.
 */
class ConciliarTraspasoTiendaCedisService
{
    /**
     * @return list<PedidoBmaTraspasoDiscrepancia>
     */
    public function ejecutar(SolicitudTraspaso $solicitud, ?User $usuario = null): array
    {
        return DB::transaction(function () use ($solicitud, $usuario) {
            $solicitud = SolicitudTraspaso::query()
                ->whereKey($solicitud->id)
                ->lockForUpdate()
                ->with(['productos.revisiones'])
                ->firstOrFail();

            PedidoBmaTraspasoDiscrepancia::query()
                ->where('solicitud_traspaso_id', $solicitud->id)
                ->where('momento', SolicitudTraspasoRevisionProducto::MOMENTO_CEDIS)
                ->delete();

            $registradas = [];
            foreach ($solicitud->productos as $linea) {
                $enviadas = (int) $linea->piezas;
                if ($enviadas <= 0) {
                    continue;
                }

                $recibidas = $linea->revisiones
                    ->where('momento', SolicitudTraspasoRevisionProducto::MOMENTO_CEDIS)
                    ->where('estado_fisico', 'bueno')
                    ->count();

                if ($recibidas === $enviadas) {
                    continue;
                }

                $registradas[] = PedidoBmaTraspasoDiscrepancia::query()->create([
                    'solicitud_traspaso_id' => $solicitud->id,
                    'solicitud_traspaso_producto_id' => $linea->id,
                    'pedido_bma_origen_id' => $linea->pedido_bma_origen_id,
                    'sku' => (string) $linea->sku,
                    'piezas_tienda' => $enviadas,
                    'piezas_cedis' => $recibidas,
                    'momento' => SolicitudTraspasoRevisionProducto::MOMENTO_CEDIS,
                    'registrado_por_id' => $usuario?->id,
                    'registrado_at' => now(),
                    'datos' => [
                        'descripcion' => $linea->descripcion,
                    ],
                ]);
            }

            return $registradas;
        });
    }
}
