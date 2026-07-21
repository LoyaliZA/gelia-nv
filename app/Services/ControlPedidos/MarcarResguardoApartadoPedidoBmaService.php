<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MarcarResguardoApartadoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  list<UploadedFile>  $evidencias
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, array $evidencias, string $detalle = ''): PedidoBma
    {
        if (! $pedido->es_resguardo) {
            throw new \RuntimeException('Solo se puede marcar apartado en pedidos en resguardo.');
        }

        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_EN_CEDIS) {
            throw new \RuntimeException('Solo se puede marcar apartado cuando el pedido está en CEDIS.');
        }

        if ($pedido->resguardo_apartado_at) {
            throw new \RuntimeException('Este resguardo ya fue marcado como apartado.');
        }

        $archivos = array_values(array_filter(
            $evidencias,
            fn ($f) => $f instanceof UploadedFile && $f->isValid()
        ));

        if ($archivos === []) {
            throw new \InvalidArgumentException('Debe adjuntar al menos una evidencia fotográfica del apartado.');
        }

        $detalle = trim($detalle);

        return DB::transaction(function () use ($pedido, $usuarioId, $archivos, $detalle) {
            $estatus = $pedido->estatus;
            $orden = (int) $pedido->documentos()->max('orden') + 1;

            foreach ($archivos as $archivo) {
                $ruta = $archivo->store("pedidos_bma/apartados/{$pedido->id}", 'public');
                $pedido->documentos()->create([
                    'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_APARTADO,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $archivo->getMimeType(),
                    'tamano_bytes' => $archivo->getSize(),
                    'orden' => $orden++,
                ]);
            }

            $pedido->update([
                'resguardo_apartado_at' => now(),
                'resguardo_apartado_por_id' => $usuarioId,
                'detalle_resguardo_apartado' => $detalle !== '' ? $detalle : null,
            ]);

            $comentario = 'Resguardo marcado como apartado por CEDIS (piezas apartadas).';
            if ($detalle !== '') {
                $comentario .= " Detalle: {$detalle}";
            }

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatus->id,
                $comentario
            );

            $pedido = $pedido->fresh([
                'cliente', 'estatus', 'vendedor', 'documentos', 'resguardoApartadoPor', 'almacen',
            ]);

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_resguardo_apartado',
                'CEDIS apartó las piezas de tu pedido en resguardo. Revisa la evidencia adjunta.',
                [],
                $usuarioId,
                true,
                [
                    'url' => '/control-pedidos?q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id)),
                ]
            );

            return $pedido;
        });
    }
}
