<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GestionarPdfPedidoBmaService
{
    private const MIMES_SOPORTE = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const EXTS_SOPORTE = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public function subir(PedidoBma $pedido, UploadedFile $archivo): PedidoBma
    {
        if (! $pedido->esEditablePorVendedora()) {
            throw new \RuntimeException('Solo se puede adjuntar el soporte del pedido en borrador, pesaje pendiente o rechazado.');
        }

        $this->assertSoporteValido($archivo);

        return DB::transaction(function () use ($pedido, $archivo) {
            $this->eliminarExistentes($pedido, PedidoBmaDocumento::TIPO_PDF_PEDIDO);

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
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'cajas.tipoCaja', 'cajas.tipoGuia',
            ]);
        });
    }

    public function subirAnexoPiezas(PedidoBma $pedido, UploadedFile $archivo): PedidoBma
    {
        if (! $pedido->esEditablePorVendedora() && ! $pedido->puedeSolicitarRepesaje()) {
            throw new \RuntimeException('No se puede adjuntar el anexo de piezas en el estado actual.');
        }

        if (! $pedido->tienePesajeRespondido()) {
            throw new \RuntimeException('Adjunte el anexo de piezas solo después del primer pesaje CEDIS.');
        }

        $this->assertSoporteValido($archivo);

        return DB::transaction(function () use ($pedido, $archivo) {
            $this->eliminarExistentes($pedido, PedidoBmaDocumento::TIPO_ANEXO_PIEZAS);

            $ruta = $archivo->store("pedidos_bma/anexo_piezas/{$pedido->id}", 'public');

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_ANEXO_PIEZAS,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => 0,
            ]);

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'cajas.tipoCaja', 'cajas.tipoGuia',
            ]);
        });
    }

    private function assertSoporteValido(UploadedFile $archivo): void
    {
        $mime = (string) $archivo->getMimeType();
        $ext = strtolower($archivo->getClientOriginalExtension());

        if (in_array($mime, self::MIMES_SOPORTE, true) || in_array($ext, self::EXTS_SOPORTE, true)) {
            return;
        }

        throw new \InvalidArgumentException('El archivo debe ser un PDF o una imagen (JPG, PNG o WEBP).');
    }

    private function eliminarExistentes(PedidoBma $pedido, string $tipo): void
    {
        $docs = $pedido->documentos()->where('tipo', $tipo)->get();

        foreach ($docs as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }
}
