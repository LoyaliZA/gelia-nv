<?php

namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;
use App\Models\Cliente;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\AuditoriaSolicitud;
use App\Notifications\AlertaSolicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CancelarSolicitudService
{
    public function __construct(
        private ValidarListaInferiorService $validarListaInferior,
        private AjustarMontoPorSolicitudService $ajustarMonto,
    ) {}

    public function ejecutar(SolicitudTag $solicitud, ?string $motivo = null): void
    {
        DB::transaction(function () use ($solicitud, $motivo) {
            $solicitud->loadMissing(['proceso', 'cliente', 'vendedor', 'listaRebaja']);

            if (!$solicitud->cancelacion_solicitada_at) {
                abort(422, 'Esta solicitud no tiene una solicitud de cancelación pendiente.');
            }

            $estadoCancelada = CatalogoEstadoSolicitud::where('nombre', 'Cancelada')->firstOrFail();
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;

            if ($estadoAnteriorId == $estadoCancelada->id) {
                abort(422, 'Esta solicitud ya está cancelada.');
            }

            $snapshotDiff = [];
            $estadosConBeneficios = [
                CatalogoEstadoSolicitud::idDe('Respondida'),
                CatalogoEstadoSolicitud::idDe('Verificada'),
            ];

            if (in_array($estadoAnteriorId, $estadosConBeneficios) && $solicitud->proceso?->esFinanciero()) {
                $snapshotDiff = $this->ajustarMonto->revertirBeneficios($solicitud, Auth::id());

                if ($solicitud->catalogo_lista_rebaja_id && $solicitud->cliente_id) {
                    $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($solicitud->cliente_id);
                    if ($cliente) {
                        $listaRebaja = $this->validarListaInferior->validarListaInferior(
                            $solicitud,
                            $solicitud->catalogo_lista_rebaja_id
                        );
                        $cliente->lista_actual_id = $listaRebaja->id;
                        $cliente->save();
                        $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);
                        $snapshotDiff['despues'] = $this->ajustarMonto->capturarSnapshotCliente($cliente);
                    }
                }
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
}
