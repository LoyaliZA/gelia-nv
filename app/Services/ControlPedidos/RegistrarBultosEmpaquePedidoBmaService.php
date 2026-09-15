<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaBultoEmpaque;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RegistrarBultosEmpaquePedidoBmaService
{
    /**
     * @param  list<array{foto_bulto: UploadedFile, foto_ticket: UploadedFile}>  $bultos
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, array $bultos): PedidoBma
    {
        if (! $pedido->requiereSucursalDestino()) {
            throw new \InvalidArgumentException('Este pedido no requiere registro de bultos de empaque.');
        }

        if ($bultos === []) {
            throw new \InvalidArgumentException('Debe registrar al menos un bulto con evidencia.');
        }

        if ($pedido->bultosEmpaque()->exists()) {
            throw new \RuntimeException('El pedido ya tiene bultos de empaque registrados.');
        }

        $ordenDoc = (int) $pedido->documentos()->max('orden') + 1;
        $ahora = now();

        foreach (array_values($bultos) as $idx => $fila) {
            $fotoBulto = $fila['foto_bulto'] ?? null;
            $fotoTicket = $fila['foto_ticket'] ?? null;

            if (! $fotoBulto instanceof UploadedFile || ! $fotoBulto->isValid()) {
                throw new \InvalidArgumentException('Cada bulto debe incluir foto del bulto.');
            }

            if (! $fotoTicket instanceof UploadedFile || ! $fotoTicket->isValid()) {
                throw new \InvalidArgumentException('Cada bulto debe incluir foto del ticket.');
            }

            $bulto = PedidoBmaBultoEmpaque::query()->create([
                'pedido_bma_id' => $pedido->id,
                'numero' => $idx + 1,
                'empacado_por_id' => $usuarioId,
                'empacado_at' => $ahora,
            ]);

            $this->guardarDocumento($pedido, $bulto, PedidoBmaDocumento::TIPO_EVIDENCIA_BULTO_EMPAQUE, $fotoBulto, $ordenDoc++);
            $this->guardarDocumento($pedido, $bulto, PedidoBmaDocumento::TIPO_EVIDENCIA_TICKET_BULTO_EMPAQUE, $fotoTicket, $ordenDoc++);
        }

        $pedido->update(['numero_cajas' => count($bultos)]);

        return $pedido->fresh(['bultosEmpaque.documentos', 'documentos', 'origen', 'sucursalDestino']);
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
