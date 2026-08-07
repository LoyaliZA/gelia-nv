<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GestionarRemisionPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function subir(PedidoBma $pedido, UploadedFile $archivo, int $usuarioId): PedidoBma
    {
        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se puede adjuntar remisión en pedidos pendientes de revisión.');
        }

        if ($archivo->getMimeType() !== 'application/pdf' && !str_ends_with(strtolower($archivo->getClientOriginalName()), '.pdf')) {
            throw new \InvalidArgumentException('La remisión debe ser un archivo PDF.');
        }

        return DB::transaction(function () use ($pedido, $archivo, $usuarioId) {
            $this->eliminarRemisiones($pedido);

            $ruta = $archivo->store("pedidos_bma/remisiones/{$pedido->id}", 'public');
            $nombre = $archivo->getClientOriginalName();

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_REMISION,
                'ruta_archivo' => $ruta,
                'nombre_original' => $nombre,
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => 0,
            ]);

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                "Remisión adjuntada: {$nombre}",
                AccionesHistorialPedidoBma::CARGA_REMISION,
                ['ruta' => $ruta, 'nombre' => $nombre]
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
            ]);
        });
    }

    public function eliminar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se puede eliminar la remisión en pedidos pendientes de revisión.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $remision = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->first();
            $nombre = $remision?->nombre_original;

            $this->eliminarRemisiones($pedido);

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                $nombre ? "Remisión eliminada: {$nombre}" : 'Remisión eliminada.',
                AccionesHistorialPedidoBma::ELIMINA_REMISION
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
