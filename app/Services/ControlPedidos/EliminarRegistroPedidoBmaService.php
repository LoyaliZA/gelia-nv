<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\AuditoriaPedidoBma;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EliminarRegistroPedidoBmaService
{
    public function __construct(
        private SincronizarAplicacionesPedidoSafService $safPedido,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $motivo): void
    {
        $pedido->loadMissing([
            'estatus', 'vendedor', 'cliente', 'documentos', 'pagosExhibicion',
            'revisionesProducto', 'cajas', 'origen',
        ]);

        DB::transaction(function () use ($pedido, $usuarioId, $motivo) {
            AuditoriaPedidoBma::query()->create([
                'pedido_bma_id' => $pedido->id,
                'usuario_id' => $usuarioId,
                'accion' => AuditoriaPedidoBma::ACCION_ELIMINACION,
                'motivo' => "ELIMINACIÓN DE REGISTRO: {$motivo}",
                'fase_ciclo' => $pedido->estatus?->fase_ciclo,
                'folio' => $pedido->folio,
                'folio_remision' => $pedido->folio_remision,
                'estatus_id' => $pedido->catalogo_estatus_pedido_id,
                'datos_snapshot' => $pedido->toArray(),
            ]);

            $this->safPedido->liberarReservasPendientes($pedido, $usuarioId);

            foreach ($pedido->documentos as $documento) {
                Storage::disk('public')->delete($documento->ruta_archivo);
            }

            $pedido->update([
                'eliminacion_registro_at' => now(),
                'eliminacion_registro_por_id' => $usuarioId,
            ]);

            $pedido->delete();
        });
    }
}
