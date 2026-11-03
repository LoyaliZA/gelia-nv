<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AC-03: no pasa del día siguiente a la orden. La hora dentro de ese día no está definida.
 * Se cancela al comenzar el día posterior al de tolerancia, en la zona de preparación.
 */
final class CancelarCreditoSinRemisionTiendaService
{
    public const HORA_SIN_DEFINIR = 'La hora de corte dentro del día siguiente no está definida. La cancelación corre al iniciar el día posterior.';

    public function __construct(
        private CancelarPedidoBmaService $cancelar,
        private CompromisoInventarioTienda $inventario,
    ) {}

    public function ejecutar(): int
    {
        $zona = app(PreparacionTiendaConfig::class)->zonaHoraria();
        $hoy = Carbon::now($zona)->startOfDay();
        $cancelados = 0;

        PedidoBma::query()
            ->whereNull('numero_remision')
            ->whereNull('cancelado_at')
            ->orderBy('id')
            ->each(function (PedidoBma $pedido) use ($zona, $hoy, &$cancelados) {
                $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
                $resultado = $json['credito_wizerp']['resultado'] ?? null;
                if (! in_array($resultado, ['aprobado', 'bloqueado'], true)) {
                    return;
                }
                $fecha = $pedido->fecha ?: $pedido->created_at;
                if (! $fecha) {
                    return;
                }
                $limite = Carbon::parse($fecha, $zona)->startOfDay()->addDays(2);
                if ($hoy->lt($limite)) {
                    return;
                }
                if ($pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_CANCELADO) {
                    return;
                }

                DB::transaction(function () use ($pedido) {
                    $this->inventario->liberarPedido($pedido);
                    $this->cancelar->ejecutar($pedido, (int) ($pedido->vendedor_id ?: 1), [
                        'motivo' => 'pago_no_concluye',
                        'comentario' => self::HORA_SIN_DEFINIR.' Sin remisión al terminar el día siguiente.',
                        'resolucion_financiera' => CancelarPedidoBmaService::RESOLUCION_PENDIENTE,
                    ]);
                });
                $cancelados++;
            });

        return $cancelados;
    }
}
