<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;

class GestionarSinExistenciaCedisPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  array{
     *   descripcion_producto: string,
     *   comentario: string,
     *   sku?: string|null,
     *   producto_id?: int|null
     * }  $datos
     */
    public function reportar(PedidoBma $pedido, int $usuarioId, array $datos): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'revisionesProducto']);

        if (! $pedido->esGestionablePorCedis() || $pedido->empacado_at !== null) {
            throw new \RuntimeException('Solo se puede reportar sin existencias en pendiente de empaque (antes de empacar).');
        }

        $desc = trim((string) ($datos['descripcion_producto'] ?? ''));
        $comentario = trim((string) ($datos['comentario'] ?? ''));
        if ($desc === '' || $comentario === '') {
            throw new \InvalidArgumentException('Indique el producto y un comentario para Ventas.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $desc, $comentario, $datos) {
            $orden = (int) $pedido->revisionesProducto()->max('orden') + 1;
            PedidoBmaRevisionProducto::create([
                'pedido_bma_id' => $pedido->id,
                'orden' => $orden,
                'descripcion_producto' => mb_substr($desc, 0, 255),
                'producto_id' => isset($datos['producto_id']) && $datos['producto_id'] !== ''
                    ? (int) $datos['producto_id']
                    : null,
                'sku' => ($sku = trim((string) ($datos['sku'] ?? ''))) !== '' ? mb_substr($sku, 0, 64) : null,
                'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
                'comentario' => $comentario,
                'unica_pieza' => false,
                'mejor_ejemplar' => false,
            ]);

            $pedido->update([
                'tiene_observaciones_fisicas' => true,
                'estado_fisico_general' => $this->peorEstado(
                    $pedido->fresh('revisionesProducto')->revisionesProducto->pluck('estado_fisico')->all()
                ),
            ]);

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                "CEDIS reportó sin existencias: {$desc}. {$comentario}",
                AccionesHistorialPedidoBma::REPORTE_SIN_EXISTENCIA
            );

            $this->notificarSinExistencia($pedido->fresh(), $usuarioId);

            return $pedido->fresh(['estatus', 'revisionesProducto', 'documentos']);
        });
    }

    public function confirmarStock(PedidoBma $pedido, int $usuarioId, int $revisionId, ?string $nota = null): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'revisionesProducto']);

        if (! $pedido->esGestionablePorCedis()) {
            throw new \RuntimeException('CEDIS no puede confirmar existencias en esta fase.');
        }

        $revision = $pedido->revisionesProducto->firstWhere('id', $revisionId);
        if (! $revision || ! $revision->estaSinExistenciaAbierta()) {
            throw new \InvalidArgumentException('No hay una pieza sin existencias abierta.');
        }

        $nota = trim((string) $nota);

        return DB::transaction(function () use ($pedido, $usuarioId, $revision, $nota) {
            $revision->update([
                'resolucion' => PedidoBmaRevisionProducto::RESOLUCION_STOCK_OK,
                'resolucion_nota' => $nota !== '' ? $nota : 'CEDIS confirmó que ya hay existencias.',
                'resolucion_por_id' => $usuarioId,
                'resolucion_at' => now(),
            ]);

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                sprintf(
                    'CEDIS confirmó existencias: %s. Estado físico se conserva (Sin existencias).%s',
                    $revision->descripcion_producto,
                    $nota !== '' ? ' '.$nota : ''
                ),
                AccionesHistorialPedidoBma::STOCK_SIN_EXISTENCIA
            );

            return $pedido->fresh(['estatus', 'revisionesProducto', 'documentos']);
        });
    }

    private function notificarSinExistencia(PedidoBma $pedido, int $usuarioId): void
    {
        $folioQ = $pedido->folio_remision ?: $pedido->folio ?: '';
        $this->notificarService->ejecutar(
            $pedido,
            'pedido_sin_existencia',
            'CEDIS reportó producto sin existencias. El pedido está detenido hasta que elijas una acción.',
            [],
            $usuarioId,
            true,
            [
                'url' => '/control-pedidos?tab=SIN_EXISTENCIA'.($folioQ !== '' ? '&q='.rawurlencode($folioQ) : ''),
                'con_sin_existencia' => true,
            ]
        );
    }

    /** @param  list<string>  $estados */
    private function peorEstado(array $estados): string
    {
        $severidad = [
            PedidoBmaRevisionProducto::ESTADO_BUENO => 0,
            PedidoBmaRevisionProducto::ESTADO_REGULAR => 1,
            PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA => 2,
            PedidoBmaRevisionProducto::ESTADO_MALO => 3,
            PedidoBmaRevisionProducto::ESTADO_DANADO => 4,
        ];
        $peor = PedidoBmaRevisionProducto::ESTADO_BUENO;
        foreach ($estados as $estado) {
            if (($severidad[$estado] ?? -1) > ($severidad[$peor] ?? -1)) {
                $peor = $estado;
            }
        }

        return $peor;
    }
}
