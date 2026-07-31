<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GestionarPdfPedidoBmaService
{
    public function subir(PedidoBma $pedido, UploadedFile $archivo): PedidoBma
    {
        if (! $pedido->esEditablePorVendedora()) {
            throw new \RuntimeException('Solo se puede adjuntar el PDF del pedido en borrador o rechazado.');
        }

        if ($archivo->getMimeType() !== 'application/pdf' && ! str_ends_with(strtolower($archivo->getClientOriginalName()), '.pdf')) {
            throw new \InvalidArgumentException('El PDF del pedido debe ser un archivo PDF.');
        }

        return DB::transaction(function () use ($pedido, $archivo) {
            $this->eliminarExistentes($pedido);

            $ruta = $archivo->store("pedidos_bma/pdf_pedido/{$pedido->id}", 'public');

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_PDF_PEDIDO,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => 0,
            ]);

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'cajas.tipoCaja',
            ]);
        });
    }

    private function eliminarExistentes(PedidoBma $pedido): void
    {
        $docs = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_PDF_PEDIDO)->get();

        foreach ($docs as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }
}
