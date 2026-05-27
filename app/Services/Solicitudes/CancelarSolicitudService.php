<?php

namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;
use App\Models\Cliente;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\AuditoriaSolicitud;
use App\Models\User;
use App\Notifications\AlertaSolicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CancelarSolicitudService
{
    public function ejecutar(SolicitudTag $solicitud, ?string $motivo = null): void
    {
        DB::transaction(function () use ($solicitud, $motivo) {
            $solicitud->loadMissing(['proceso', 'cliente', 'vendedor']);

            if (!$solicitud->cancelacion_solicitada_at) {
                abort(422, 'Esta solicitud no tiene una solicitud de cancelación pendiente.');
            }

            $estadoCancelada = CatalogoEstadoSolicitud::where('nombre', 'Cancelada')->firstOrFail();
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;

            if ($estadoAnteriorId == $estadoCancelada->id) {
                abort(422, 'Esta solicitud ya está cancelada.');
            }

            $snapshotDiff = [];
            $estadosConBeneficios = [2, 3];

            if (in_array($estadoAnteriorId, $estadosConBeneficios) && $solicitud->proceso?->esFinanciero()) {
                $snapshotDiff = $this->revertirBeneficiosCliente($solicitud);
            }

            $motivoFinal = $motivo ?: $solicitud->motivo_cancelacion;

            $solicitud->update([
                'catalogo_estado_solicitud_id' => $estadoCancelada->id,
                'motivo_cancelacion' => $motivoFinal,
                'pago_confirmado' => false,
                'cancelacion_solicitada_at' => null,
            ]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoCancelada->id,
                'motivo_reporte' => 'CANCELACIÓN CONFIRMADA: ' . ($motivoFinal ?: 'Sin motivo especificado'),
                'datos_snapshot' => !empty($snapshotDiff) ? $snapshotDiff : null,
            ]);

            if ($solicitud->vendedor) {
                Notification::send(
                    collect([$solicitud->vendedor]),
                    new AlertaSolicitud(
                        $solicitud,
                        'cancelada',
                        'Tu solicitud FOL-' . $solicitud->id . ' ha sido cancelada por el área administrativa.'
                    )
                );
            }
        });
    }

    private function revertirBeneficiosCliente(SolicitudTag $solicitud): array
    {
        if (!$solicitud->cliente_id) {
            return [];
        }

        $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($solicitud->cliente_id);
        if (!$cliente) {
            return [];
        }

        $antes = $this->capturarSnapshotCliente($cliente);

        $auditoriaAprobacion = AuditoriaSolicitud::where('solicitud_id', $solicitud->id)
            ->whereIn('estado_nuevo_id', [2, 3])
            ->whereNotNull('datos_snapshot')
            ->orderBy('id')
            ->first();

        $snapshotAntes = $auditoriaAprobacion?->datos_snapshot['antes'] ?? null;

        if ($snapshotAntes) {
            $this->restaurarClienteDesdeSnapshot($cliente, $snapshotAntes);
        } else {
            $nuevoMonto = ($cliente->monto_venta_actual ?? 0) - ($solicitud->monto_cotizado ?? 0);
            $cliente->monto_venta_actual = max(0, $nuevoMonto);
            $this->recalcularListaCliente($cliente);

            if ($solicitud->catalogo_tipo_cliente_id) {
                $cliente->catalogo_tipo_cliente_id = null;
            }
        }

        $cliente->save();
        $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);

        return [
            'antes' => $antes,
            'despues' => $this->capturarSnapshotCliente($cliente),
        ];
    }

    private function restaurarClienteDesdeSnapshot(Cliente $cliente, array $snapshot): void
    {
        if (array_key_exists('monto_venta', $snapshot)) {
            $cliente->monto_venta_actual = $snapshot['monto_venta'];
        }
        if (array_key_exists('lista_id', $snapshot)) {
            $cliente->lista_actual_id = $snapshot['lista_id'];
        }
        if (array_key_exists('tipo_cliente_id', $snapshot)) {
            $cliente->catalogo_tipo_cliente_id = $snapshot['tipo_cliente_id'];
        }
        if (array_key_exists('tag_vendedor_id', $snapshot)) {
            $cliente->vendedor_id = $snapshot['tag_vendedor_id'];
        }
    }

    private function capturarSnapshotCliente(Cliente $cliente): array
    {
        return [
            'monto_venta' => $cliente->monto_venta_actual,
            'lista_id' => $cliente->lista_actual_id,
            'lista_nombre' => $cliente->listaDescuento?->nombre,
            'tag_vendedor_id' => $cliente->vendedor_id,
            'tag_vendedor_nombre' => $cliente->vendedor?->name,
            'tipo_cliente_id' => $cliente->catalogo_tipo_cliente_id,
            'tipo_cliente_nombre' => $cliente->tipo?->nombre,
        ];
    }

    private function recalcularListaCliente(Cliente $cliente): void
    {
        $listaCalificada = CatalogoListaDescuento::where('activo', true)
            ->where('nombre', 'not like', '%COLABORADOR%')
            ->where('nombre', 'not like', '%PLATAFORMAS%')
            ->where('monto_requerido', '<=', $cliente->monto_venta_actual)
            ->orderBy('monto_requerido', 'desc')
            ->first();

        $cliente->lista_actual_id = $listaCalificada ? $listaCalificada->id : null;
    }
}
