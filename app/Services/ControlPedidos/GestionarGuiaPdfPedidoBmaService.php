<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GestionarGuiaPdfPedidoBmaService
{
    public function subir(PedidoBma $pedido, UploadedFile $archivo): PedidoBma
    {
        if (!$pedido->puedeGestionarGuiaPdf()) {
            throw new \RuntimeException('Solo se puede adjuntar PDF de guía en pedidos pendientes de guía o enviados.');
        }

        if ($archivo->getMimeType() !== 'application/pdf' && !str_ends_with(strtolower($archivo->getClientOriginalName()), '.pdf')) {
            throw new \InvalidArgumentException('La guía debe ser un archivo PDF.');
        }

        return DB::transaction(function () use ($pedido, $archivo) {
            $this->eliminarGuiasPdf($pedido);

            $ruta = $archivo->store("pedidos_bma/guias/{$pedido->id}", 'public');

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_GUIA,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => 0,
            ]);

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'paqueteria', 'vendedor',
            ]);
        });
    }

    public function eliminar(PedidoBma $pedido): PedidoBma
    {
        if (!$pedido->puedeGestionarGuiaPdf()) {
            throw new \RuntimeException('Solo se puede eliminar el PDF de guía en pedidos pendientes de guía o enviados.');
        }

        return DB::transaction(function () use ($pedido) {
            $this->eliminarGuiasPdf($pedido);

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
