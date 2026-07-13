<?php

namespace App\Services\ControlPedidos;

use App\Models\Cliente;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActualizarPedidoBmaService
{
    use ResuelveDatosPedidoBma;

    public function ejecutar(PedidoBma $pedido, array $datos, int $usuarioId): PedidoBma
    {
        if (!$pedido->esEditablePorVendedora()) {
            throw new \RuntimeException('Este pedido no puede editarse en su estado actual.');
        }

        return DB::transaction(function () use ($pedido, $datos) {
            $clienteId = $pedido->cliente_id;
            if (!empty($datos['cliente_id'])) {
                $clienteId = (int) $datos['cliente_id'];
            } elseif (!empty($datos['numero_cliente'])) {
                $cliente = Cliente::where('numero_cliente', $datos['numero_cliente'])->first();
                if ($cliente) {
                    $clienteId = $cliente->id;
                }
            }

            $pedido->update(array_merge(
                $this->atributosPedidoBase($datos),
                [
                    'cliente_id' => $clienteId,
                    'motivo_rechazo' => $pedido->estatus?->fase_ciclo === 'RECHAZADO_VENDEDORA' ? null : $pedido->motivo_rechazo,
                ]
            ));

            if (!empty($datos['documentos_eliminar']) && is_array($datos['documentos_eliminar'])) {
                $this->eliminarDocumentos($pedido, $datos['documentos_eliminar']);
            }

            if (!empty($datos['comprobantes'])) {
                $this->agregarDocumentos($pedido, $datos['comprobantes']);
            }

            return $pedido->fresh(['cliente', 'estatus', 'envioTienda', 'documentos', 'almacen', 'banco']);
        });
    }

    private function eliminarDocumentos(PedidoBma $pedido, array $ids): void
    {
        $documentos = PedidoBmaDocumento::where('pedido_bma_id', $pedido->id)
            ->whereIn('id', $ids)
            ->get();

        foreach ($documentos as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }

    private function agregarDocumentos(PedidoBma $pedido, array $archivos): void
    {
        $orden = (int) $pedido->documentos()->max('orden') + 1;

        foreach ($archivos as $archivo) {
            if (!$archivo instanceof UploadedFile || !$archivo->isValid()) {
                continue;
            }

            $ruta = $archivo->store("pedidos_bma/comprobantes/{$pedido->id}", 'public');

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_COMPROBANTE,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => $orden++,
            ]);
        }
    }
}
