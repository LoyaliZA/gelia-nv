<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\AuditoriaPedidoBma;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurarRegistroPedidoBmaService
{
    public function ejecutar(int $pedidoId, int $usuarioId): PedidoBma
    {
        $pedido = PedidoBma::withTrashed()->find($pedidoId);

        if (! $pedido) {
            throw ValidationException::withMessages(['pedido' => 'El pedido no existe.']);
        }

        if ($pedido->eliminacion_registro_at === null) {
            throw ValidationException::withMessages([
                'pedido' => 'Este pedido no fue eliminado como registro administrativo y no puede restaurarse desde la papelera.',
            ]);
        }

        if (! $pedido->trashed()) {
            throw ValidationException::withMessages(['pedido' => 'El pedido no está eliminado.']);
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $pedido->loadMissing('estatus');

            AuditoriaPedidoBma::query()->create([
                'pedido_bma_id' => $pedido->id,
                'usuario_id' => $usuarioId,
                'accion' => AuditoriaPedidoBma::ACCION_RESTAURACION,
                'motivo' => 'RESTAURACIÓN DE REGISTRO (INFORMATIVO)',
                'fase_ciclo' => $pedido->estatus?->fase_ciclo,
                'folio' => $pedido->folio,
                'folio_remision' => $pedido->folio_remision,
                'estatus_id' => $pedido->catalogo_estatus_pedido_id,
                'datos_snapshot' => $pedido->toArray(),
            ]);

            $pedido->restore();
            $pedido->update([
                'eliminacion_registro_at' => null,
                'eliminacion_registro_por_id' => null,
            ]);

            return $pedido->fresh(['estatus', 'cliente', 'vendedor']);
        });
    }
}
