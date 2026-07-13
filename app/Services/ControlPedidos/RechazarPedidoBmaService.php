<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RechazarPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $motivo): PedidoBma
    {
        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se pueden rechazar pedidos pendientes de revisión.');
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new \InvalidArgumentException('Debe indicar el motivo del rechazo.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $motivo) {
            $estatusAnterior = $pedido->estatus;
            $estatusRechazado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA)
                ?? CatalogoEstatusPedido::porCodigo('NARANJA');

            if (!$estatusRechazado) {
                throw new \RuntimeException('No se encontró el estatus RECHAZADO_VENDEDORA.');
            }

            $this->eliminarRemisiones($pedido);

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusRechazado->id,
                'motivo_rechazo' => $motivo,
                'pago_validado_at' => null,
                'pago_validado_por_id' => null,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusRechazado,
                'Rechazado por auxiliar: ' . $motivo
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
            ]);
        });
    }

    private function eliminarRemisiones(PedidoBma $pedido): void
    {
        $remisiones = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->get();

        foreach ($remisiones as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }
}
