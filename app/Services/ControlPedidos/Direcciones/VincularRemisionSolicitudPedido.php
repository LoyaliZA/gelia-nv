<?php

namespace App\Services\ControlPedidos\Direcciones;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\SolicitudDireccion;
use App\Services\Clientes\Direcciones\ServicioAuditoriaDireccion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VincularRemisionSolicitudPedido
{
    public function __construct(
        private ServicioAuditoriaDireccion $auditoria,
    ) {}

    public function ejecutar(SolicitudDireccion $solicitud, PedidoBma $pedido, int $usuarioId): PedidoBmaDocumento
    {
        return DB::transaction(function () use ($solicitud, $pedido, $usuarioId) {
            if ($solicitud->estado_remision !== SolicitudDireccion::REMISION_PENDING_ORDER_LINK) {
                throw new \RuntimeException('La solicitud no tiene remisión pendiente de vínculo.');
            }

            if ($solicitud->cliente_coincidente_id && (int) $solicitud->cliente_coincidente_id !== (int) $pedido->cliente_id) {
                throw new \RuntimeException('La solicitud no pertenece al mismo cliente del pedido.');
            }

            if (! $solicitud->archivo_remision || ! Storage::disk('local')->exists($solicitud->archivo_remision)) {
                throw new \RuntimeException('No se encontró el archivo de remisión de la solicitud.');
            }

            if ($pedido->tieneRemision()) {
                throw new \RuntimeException('El pedido ya tiene remisión oficial. Desvincule o rechace antes de reemplazar.');
            }

            $contenido = Storage::disk('local')->get($solicitud->archivo_remision);
            $nombre = basename($solicitud->archivo_remision);
            $destino = 'pedidos_bma/'.$pedido->id.'/remisiones/'.$nombre;
            Storage::disk('public')->put($destino, $contenido);

            $doc = PedidoBmaDocumento::query()->create([
                'pedido_bma_id' => $pedido->id,
                'tipo' => PedidoBmaDocumento::TIPO_REMISION,
                'ruta_archivo' => $destino,
                'nombre_original' => $nombre,
                'mime_type' => 'application/pdf',
                'tamano_bytes' => Storage::disk('public')->size($destino),
                'orden' => 0,
            ]);

            $solicitud->update([
                'estado_remision' => SolicitudDireccion::REMISION_LINKED,
                'notas_validacion' => trim(($solicitud->notas_validacion ?? '')."\nVinculada a pedido {$pedido->folio} por usuario {$usuarioId}"),
            ]);

            if ($solicitud->cliente_coincidente_id) {
                $this->auditoria->ejecutar(
                    $solicitud->cliente_coincidente_id,
                    'vincular_remision_pedido',
                    $usuarioId,
                    $solicitud->direccion_seleccionada_id,
                    $solicitud->id,
                    null,
                    ['pedido_bma_id' => $pedido->id, 'documento_id' => $doc->id],
                    'control_pedidos',
                );
            }

            return $doc;
        });
    }
}
