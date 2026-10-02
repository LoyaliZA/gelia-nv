<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadTareaPreparacion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cola informativa: exhibiciones pendientes de conciliar con estado de cuenta en Caja.
 * No bloquea la separación en Tienda.
 */
class ColaEstadosCuentaPreparacionService
{
    private const ESTADOS_COLA = [
        PedidoBmaPago::REVISION_PENDIENTE,
        PedidoBmaPago::REVISION_EN_REVISION,
    ];

    /**
     * @return array{total: int, casos: list<array{pedido_id: int, folio: string, tarea_id: int, exhibiciones_pendientes: int}>}
     */
    public function resumen(User $usuario, PreparacionTiendaConfig $config, int $limite = 8): array
    {
        $base = PedidoBmaTareaPreparacion::query();
        VisibilidadTareaPreparacion::filtrarTienda($base, $usuario, $config);
        $base->whereIn('estado', [
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
            PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA,
        ]);

        $base->whereHas('pedido', function (Builder $p) {
            $p->whereHas('pagosExhibicion', function (Builder $pay) {
                $pay->where('activo_para_cobertura', true)
                    ->whereIn('estado_revision', self::ESTADOS_COLA);
            });
        });

        $total = (clone $base)->count();

        $filas = (clone $base)
            ->with(['pedido:id,folio,folio_remision'])
            ->orderBy('solicitada_at')
            ->limit($limite)
            ->get(['id', 'pedido_bma_id', 'estado']);

        $casos = [];
        foreach ($filas as $tarea) {
            $pedido = $tarea->pedido;
            if (! $pedido) {
                continue;
            }
            $pendientes = $pedido->pagosExhibicion()
                ->where('activo_para_cobertura', true)
                ->whereIn('estado_revision', self::ESTADOS_COLA)
                ->count();
            $casos[] = [
                'pedido_id' => $pedido->id,
                'folio' => (string) ($pedido->folio ?: $pedido->folio_remision ?: $pedido->id),
                'tarea_id' => $tarea->id,
                'exhibiciones_pendientes' => $pendientes,
            ];
        }

        return ['total' => $total, 'casos' => $casos];
    }
}
