<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class GuardarMercanciaNacional
{
    public function ejecutar(PedidoBma $pedido, array $datos, int $usuarioId): void
    {
        if (! ($datos['guardar_mercancia_nacional'] ?? false)) {
            return;
        }

        if (! CapturaNacional::es($pedido) || ! $pedido->puedeSolicitarPesaje() || $pedido->pesaje_solicitado_at) {
            throw new \InvalidArgumentException('Use los ajustes de la consulta para modificar la mercancía enviada a CEDIS.');
        }

        $json = $pedido->solicitud_preparacion_json;
        $anteriores = collect($json['piezas_nacionales'] ?? [])->keyBy('id');
        $piezas = [];
        $archivos = [];
        $bytes = 0;
        foreach ($datos['piezas_nacionales'] ?? [] as $pieza) {
            $anterior = $anteriores->get($pieza['id']);
            $archivo = $pieza['evidencia'] ?? null;
            $documentoId = $anterior['documento_ids'][0] ?? null;
            if (empty($pieza['sku']) && ! $archivo && ! $documentoId) {
                throw new \InvalidArgumentException('Adjunte una captura o PDF a cada pieza sin SKU.');
            }
            if ($archivo instanceof UploadedFile) {
                $archivos[$pieza['id']] = $archivo;
                $bytes += $archivo->getSize();
            }
            $piezas[$pieza['id']] = [
                'id' => $pieza['id'],
                'sku' => $pieza['sku'] ?? null,
                'descripcion' => $pieza['descripcion'],
                'cantidad' => (int) $pieza['cantidad'],
                'documento_ids' => $documentoId ? [$documentoId] : [],
            ];
        }
        if ($bytes > 50 * 1024 * 1024) {
            throw new \InvalidArgumentException('Adjunte hasta 50 MB de evidencias por guardado.');
        }

        $rutas = [];
        try {
            foreach ($archivos as $id => $archivo) {
                $ruta = $archivo->store("pedidos_bma/mercancia_nacional/{$pedido->id}", 'public');
                if (! $ruta) {
                    throw new \RuntimeException('No se pudo guardar la evidencia de la pieza.');
                }
                $rutas[] = $ruta;
                $documento = $pedido->documentos()->create([
                    'tipo' => PedidoBmaDocumento::TIPO_ANEXO_PIEZAS,
                    'relacion_tipo' => CapturaNacional::RELACION_PIEZA_SOLICITADA,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $archivo->getMimeType(),
                    'tamano_bytes' => $archivo->getSize(),
                    'activo' => true,
                ]);
                $piezas[$id]['documento_ids'] = [$documento->id];
            }

            $vigentes = collect($piezas)->pluck('documento_ids')->flatten()->all();
            $pedido->documentos()->where('relacion_tipo', CapturaNacional::RELACION_PIEZA_SOLICITADA)
                ->where('activo', true)->whereNotIn('id', $vigentes)
                ->update(['activo' => false, 'sustituido_at' => now(), 'sustituido_por_id' => $usuarioId]);
            $json['piezas_nacionales'] = array_values($piezas);
            $pedido->update(['solicitud_preparacion_json' => $json]);
        } catch (\Throwable $error) {
            Storage::disk('public')->delete($rutas);

            throw $error;
        }
    }
}
