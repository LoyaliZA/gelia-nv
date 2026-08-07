<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\OperacionEmpaque;
use App\Models\ControlPedidos\OperacionEmpaqueMiembro;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class ConsolidarPedidosEmpaqueService
{
    public function __construct(
        private GenerarFolioOperacionEmpaqueService $folioService,
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    /**
     * Consolida pedidos en una operación de empaque.
     * El detalle multi-caja (`pedido_bma_cajas`) permanece en el pedido principal;
     * los agregados de cabecera (numero_cajas / peso) se suman para la operación.
     *
     * @param  list<int>  $pedidoIds
     * @param  array<int|string, int|null>  $piezasPorPedido  pedido_id => cantidad_piezas
     */
    public function ejecutar(
        array $pedidoIds,
        int $usuarioId,
        ?int $principalId = null,
        array $piezasPorPedido = []
    ): OperacionEmpaque {
        $pedidoIds = array_values(array_unique(array_map('intval', $pedidoIds)));

        if (count($pedidoIds) < 2) {
            throw new \InvalidArgumentException('Se requieren al menos dos pedidos para consolidar.');
        }

        return DB::transaction(function () use ($pedidoIds, $usuarioId, $principalId, $piezasPorPedido) {
            $pedidos = PedidoBma::query()
                ->with(['estatus', 'tipoOperacionEnvio', 'miembroOperacionEmpaque'])
                ->whereIn('id', $pedidoIds)
                ->lockForUpdate()
                ->get();

            if ($pedidos->count() !== count($pedidoIds)) {
                throw new \InvalidArgumentException('Uno o más pedidos no existen.');
            }

            $clienteId = null;
            foreach ($pedidos as $pedido) {
                $this->assertConsolidable($pedido);
                if ($clienteId === null) {
                    $clienteId = (int) $pedido->cliente_id;
                } elseif ((int) $pedido->cliente_id !== $clienteId) {
                    throw new \InvalidArgumentException('Todos los pedidos deben ser del mismo cliente.');
                }
            }

            if ($principalId !== null && !$pedidos->contains('id', $principalId)) {
                throw new \InvalidArgumentException('El pedido principal debe estar entre los seleccionados.');
            }

            if ($principalId === null) {
                $principalId = (int) $pedidos->sortByDesc(fn (PedidoBma $p) => (float) $p->total_mercancia)->first()->id;
            }

            $operacion = OperacionEmpaque::create([
                'folio_operacion' => $this->folioService->ejecutar(),
                'cliente_id' => $clienteId,
                'numero_cajas' => (int) $pedidos->sum(fn (PedidoBma $p) => (int) ($p->numero_cajas ?? 0)) ?: null,
                'peso_real_kg' => $pedidos->sum(fn (PedidoBma $p) => (float) ($p->peso_real_kg ?? 0)) ?: null,
                'estatus' => OperacionEmpaque::ESTATUS_ABIERTA,
            ]);

            $orden = 0;
            foreach ($pedidos->sortBy('id') as $pedido) {
                $piezas = $piezasPorPedido[$pedido->id] ?? $piezasPorPedido[(string) $pedido->id] ?? $pedido->cantidad_piezas;
                OperacionEmpaqueMiembro::create([
                    'operacion_empaque_id' => $operacion->id,
                    'pedido_bma_id' => $pedido->id,
                    'es_principal' => (int) $pedido->id === (int) $principalId,
                    'cantidad_piezas' => $piezas !== null && $piezas !== '' ? (int) $piezas : null,
                    'orden' => $orden++,
                ]);

                $pedido->update(['estatus_envio' => PedidoBma::ESTATUS_ENVIO_CONSOLIDADO]);

                $estatusId = $pedido->catalogo_estatus_pedido_id;
                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $estatusId,
                    $estatusId,
                    "Incluido en operación de empaque {$operacion->folio_operacion}.",
                    AccionesHistorialPedidoBma::CONSOLIDACION
                );
            }

            return $operacion->fresh([
                'cliente',
                'miembros.pedido.estatus',
                'miembros.pedido.documentos',
                'miembros.pedido.cliente',
            ]);
        });
    }

    public function assertConsolidable(PedidoBma $pedido): void
    {
        $fase = $pedido->estatus?->fase_ciclo;
        if (!in_array($fase, [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
        ], true)) {
            throw new \RuntimeException("El pedido {$pedido->folio} no está en fase empacable (CEDIS).");
        }

        if (!$pedido->tienePagoValidado() || !$pedido->tieneRemision()) {
            throw new \RuntimeException("El pedido {$pedido->folio} requiere pago validado y remisión.");
        }

        if ($pedido->es_resguardo) {
            throw new \RuntimeException("El pedido {$pedido->folio} está en resguardo; libérelo antes de consolidar.");
        }

        if ($pedido->miembroOperacionEmpaque) {
            throw new \RuntimeException("El pedido {$pedido->folio} ya pertenece a una operación de empaque.");
        }
    }
}
