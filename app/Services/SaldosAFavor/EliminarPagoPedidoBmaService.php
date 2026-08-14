<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EliminarPagoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private RegistrarPagoPedidoBmaService $pagos,
    ) {}

    public function handle(PedidoBmaPago $pago, ?int $usuarioId = null): void
    {
        $pedido = $pago->pedido;
        if (! $pedido) {
            throw new RuntimeException('La exhibición no tiene pedido asociado.');
        }
        $pedido->loadMissing('estatus');

        if (! $pedido->puedeEditarExhibicionesPago()) {
            throw new RuntimeException('No se puede eliminar exhibiciones en el estado actual del pedido.');
        }

        DB::transaction(function () use ($pago, $pedido, $usuarioId) {
            $numero = $pago->numero_exhibicion;
            $monto = (float) $pago->monto;
            $ruta = $pago->ruta_archivo;

            $pago->delete();

            if ($ruta) {
                Storage::disk('public')->delete($ruta);
            }

            if ($usuarioId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    sprintf(
                        'Exhibición #%d eliminada ($%s).',
                        $numero,
                        number_format($monto, 2, '.', ',')
                    ),
                    AccionesHistorialPedidoBma::BAJA_EXHIBICION_PAGO
                );
            }

            $this->pagos->reconciliarExcedenteTrasExhibicion($pedido->fresh(), $usuarioId);
        });
    }
}
