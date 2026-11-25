<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaBultoEmpaque;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Support\ControlPedidos\CustodiaDocumentalTienda;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RegistrarBultosEmpaquePedidoBmaService
{
    /**
     * @param  list<array{foto_bulto: UploadedFile, foto_ticket: UploadedFile}>  $bultos
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, array $bultos): PedidoBma
    {
        if (! $pedido->requiereBultosEmpaque()) {
            throw new \InvalidArgumentException('Este pedido no requiere registro de bultos de empaque.');
        }

        if ($bultos === []) {
            throw new \InvalidArgumentException('Debe registrar al menos un bulto con evidencia.');
        }

        if ($pedido->bultosEmpaque()->exists()) {
            throw new \RuntimeException('El pedido ya tiene bultos de empaque registrados.');
        }

        $soloBolsa = CustodiaDocumentalTienda::aplica($pedido);
        $ordenDoc = (int) $pedido->documentos()->max('orden') + 1;
        $ahora = now();

        foreach (array_values($bultos) as $idx => $fila) {
            $fotoBulto = $fila['foto_bulto'] ?? null;
            $fotoTicket = $fila['foto_ticket'] ?? null;
            $usarRemisionPdf = ! empty($fila['usar_remision_pdf']);
            if ($usarRemisionPdf && ! $fotoTicket instanceof UploadedFile && ! $pedido->tieneRemision()) {
                throw new \InvalidArgumentException('El pedido no tiene remisión en PDF adjunta. Tome la foto de la remisión.');
            }

            if (! $fotoBulto instanceof UploadedFile || ! $fotoBulto->isValid()) {
                throw new \InvalidArgumentException('Cada empaque debe incluir foto del pedido.');
            }

            if ((! $fotoTicket instanceof UploadedFile || ! $fotoTicket->isValid()) && ! $usarRemisionPdf && ! $soloBolsa) {
                throw new \InvalidArgumentException('Cada empaque debe incluir foto de la remisión.');
            }

            $tipo = $soloBolsa ? 'bolsa' : ($fila['tipo_bulto'] ?? null);
            if ($tipo !== null && ! in_array($tipo, ['caja', 'bolsa'], true)) {
                throw new \InvalidArgumentException('Indique si cada bulto es caja o bolsa.');
            }

            $bulto = PedidoBmaBultoEmpaque::query()->create([
                'pedido_bma_id' => $pedido->id,
                'numero' => $idx + 1,
                'tipo_bulto' => $tipo,
                'empacado_por_id' => $usuarioId,
                'empacado_at' => $ahora,
            ]);

            $this->guardarDocumento($pedido, $bulto, PedidoBmaDocumento::TIPO_EVIDENCIA_BULTO_EMPAQUE, $fotoBulto, $ordenDoc++);
            if ($fotoTicket instanceof UploadedFile) {
                $this->guardarDocumento($pedido, $bulto, PedidoBmaDocumento::TIPO_EVIDENCIA_TICKET_BULTO_EMPAQUE, $fotoTicket, $ordenDoc++);
            } elseif ($usarRemisionPdf) {
                $this->copiarRemisionPrincipal($pedido, $bulto, $ordenDoc++);
            }
        }

        $pedido->update(['numero_cajas' => count($bultos)]);

        return $pedido->fresh(['bultosEmpaque.documentos', 'documentos', 'origen', 'sucursalDestino']);
    }

    /** La remisión principal ya adjunta al pedido (PDF) sirve de evidencia; se copia el archivo para no compartir su ruta. */
    private function copiarRemisionPrincipal(PedidoBma $pedido, PedidoBmaBultoEmpaque $bulto, int $orden): void
    {
        $remision = $pedido->remision()->first();
        if (! $remision || ! Storage::disk('public')->exists($remision->ruta_archivo)) {
            throw new \InvalidArgumentException('No se encontró el archivo de la remisión principal. Tome la foto de la remisión.');
        }
        $extension = pathinfo($remision->ruta_archivo, PATHINFO_EXTENSION) ?: 'pdf';
        $ruta = "pedidos_bma/bultos_empaque/{$pedido->id}/remision-principal-{$bulto->id}.{$extension}";
        Storage::disk('public')->copy($remision->ruta_archivo, $ruta);
        $pedido->documentos()->create([
            'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_TICKET_BULTO_EMPAQUE,
            'ruta_archivo' => $ruta,
            'nombre_original' => $remision->nombre_original ?: 'remision-principal.pdf',
            'mime_type' => $remision->mime_type,
            'tamano_bytes' => $remision->tamano_bytes,
            'orden' => $orden,
            'relacion_tipo' => PedidoBmaDocumento::RELACION_BULTO_EMPAQUE,
            'relacion_id' => $bulto->id,
        ]);
    }

    private function guardarDocumento(
        PedidoBma $pedido,
        PedidoBmaBultoEmpaque $bulto,
        string $tipo,
        UploadedFile $archivo,
        int $orden
    ): void {
        $ruta = $archivo->store("pedidos_bma/bultos_empaque/{$pedido->id}", 'public');

        $pedido->documentos()->create([
            'tipo' => $tipo,
            'ruta_archivo' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'orden' => $orden,
            'relacion_tipo' => PedidoBmaDocumento::RELACION_BULTO_EMPAQUE,
            'relacion_id' => $bulto->id,
        ]);
    }
}
