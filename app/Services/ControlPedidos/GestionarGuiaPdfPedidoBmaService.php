<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GestionarGuiaPdfPedidoBmaService
{
    public function __construct(
        private AvanzarColaErroresPedidoBmaService $colaErroresService,
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function subir(PedidoBma $pedido, UploadedFile $archivo, int $usuarioId): PedidoBma
    {
        if (!$pedido->puedeGestionarGuiaPdf()) {
            throw new \RuntimeException('Solo se puede adjuntar PDF de guía en pedidos pendientes de guía o enviados.');
        }

        if ($archivo->getMimeType() !== 'application/pdf' && !str_ends_with(strtolower($archivo->getClientOriginalName()), '.pdf')) {
            throw new \InvalidArgumentException('La guía debe ser un archivo PDF.');
        }

        return DB::transaction(function () use ($pedido, $archivo, $usuarioId) {
            $this->eliminarGuiasPdf($pedido);

            $ruta = $archivo->store("pedidos_bma/guias/{$pedido->id}", 'public');
            $nombre = $archivo->getClientOriginalName();

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_GUIA,
                'ruta_archivo' => $ruta,
                'nombre_original' => $nombre,
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => 0,
            ]);

            if (! empty($pedido->campos_incorrectos)) {
                $restantes = $this->colaErroresService->quitarCampos(
                    $pedido,
                    ['guia_pdf'],
                    $usuarioId,
                    'PDF de guía cargado'
                );
                $pedido->update(
                    $restantes === []
                        ? $this->colaErroresService->attrsColaVacia()
                        : $this->colaErroresService->attrsColaPendiente($restantes)
                );
            }

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                "PDF de guía adjuntado: {$nombre}",
                AccionesHistorialPedidoBma::CARGA_GUIA_PDF,
                ['ruta' => $ruta, 'nombre' => $nombre]
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'paqueteria', 'vendedor',
            ]);
        });
    }

    public function eliminar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->puedeGestionarGuiaPdf()) {
            throw new \RuntimeException('Solo se puede eliminar el PDF de guía en pedidos pendientes de guía o enviados.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $guia = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->first();
            $nombre = $guia?->nombre_original;

            $this->eliminarGuiasPdf($pedido);

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                $nombre ? "PDF de guía eliminado: {$nombre}" : 'PDF de guía eliminado.',
                AccionesHistorialPedidoBma::ELIMINA_GUIA_PDF
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'paqueteria', 'vendedor',
            ]);
        });
    }

    private function eliminarGuiasPdf(PedidoBma $pedido): void
    {
        $guias = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->get();

        foreach ($guias as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }
}
