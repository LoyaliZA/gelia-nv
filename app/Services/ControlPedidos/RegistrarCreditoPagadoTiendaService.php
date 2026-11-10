<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Quien registró el pedido confirma que el cliente liquidó el crédito vencido. */
final class RegistrarCreditoPagadoTiendaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
    ) {}

    public function ejecutar(PedidoBma $pedido, User $usuario, ?UploadedFile $evidencia = null): PedidoBma
    {
        if ($evidencia && ! $evidencia->isValid()) {
            throw ValidationException::withMessages(['evidencia' => 'No se pudo leer la evidencia.']);
        }

        return DB::transaction(function () use ($pedido, $usuario, $evidencia) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->load('estatus');
            if (! $pedido->esEditablePorVendedora() || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
                throw new \RuntimeException('Solo quien registró el pedido puede confirmar que el crédito fue pagado.');
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            if (($json['cobertura_tienda']['tipo'] ?? null) !== 'credito' || ($json['credito_wizerp']['resultado'] ?? null) !== 'vencido') {
                throw new \InvalidArgumentException('Este pedido no está devuelto por crédito vencido.');
            }

            $documentoId = null;
            if ($evidencia) {
                $ruta = $evidencia->store("pedidos_bma/credito/{$pedido->id}", 'public');
                $documento = $pedido->documentos()->create([
                    'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $evidencia->getClientOriginalName(),
                    'mime_type' => $evidencia->getMimeType(),
                    'tamano_bytes' => $evidencia->getSize(),
                    'orden' => (int) $pedido->documentos()->max('orden') + 1,
                    'comentario' => 'Crédito pagado',
                    'activo' => true,
                ]);
                $documentoId = $documento->id;
            }

            $json['credito_wizerp'] = array_merge($json['credito_wizerp'] ?? [], [
                'resultado' => 'pagado',
                'pagado_at' => now()->toIso8601String(),
                'pagado_por_id' => $usuario->id,
                'evidencia_id' => $documentoId,
            ]);
            $anterior = $pedido->estatus;
            $nuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);
            if (! $nuevo) {
                throw new \RuntimeException('No se encontró el estatus de revisión.');
            }
            MaquinaEstadosPedidoBma::assertTransicion($anterior?->fase_ciclo, CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);
            $pedido->update([
                'solicitud_preparacion_json' => $json,
                'catalogo_estatus_pedido_id' => $nuevo->id,
                'motivo_rechazo' => null,
            ]);
            $this->historial->registrarTransicion(
                $pedido->id,
                $usuario->id,
                $anterior,
                $nuevo,
                'Crédito pagado. El pedido volvió a revisión.',
                AccionesHistorialPedidoBma::ENVIO_AUXILIAR
            );
            $fresco = $pedido->fresh(['cliente', 'estatus', 'vendedor']);
            $this->notificar->ejecutar(
                $fresco,
                'pedido_pendiente_auxiliar',
                'Crédito pagado. El pedido volvió a revisión.',
                ['control_pedidos.auditar'],
                $usuario->id,
                false
            );

            return $fresco;
        });
    }
}
