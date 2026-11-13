<?php

namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;
use App\Models\Cliente;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\AuditoriaSolicitud;
use App\Notifications\AlertaSolicitud;
use App\Services\Clientes\ClasificacionComercialCliente;
use App\Services\Escalonamiento\ConciliacionSolicitudEscalonamiento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CancelarSolicitudService
{
    public function __construct(
        private ValidarListaInferiorService $validarListaInferior,
        private AjustarMontoPorSolicitudService $ajustarMonto,
        private ConciliacionSolicitudEscalonamiento $conciliacion,
    ) {}

    public function ejecutar(SolicitudTag $solicitud, ?string $motivo = null): void
    {
        DB::transaction(function () use ($solicitud, $motivo) {
            if ($solicitud->cliente_id) {
                $this->ajustarMonto->prepararBloqueo((int) $solicitud->cliente_id);
            }
            $solicitud = SolicitudTag::query()->lockForUpdate()->findOrFail($solicitud->id);
            $solicitud->loadMissing(['proceso', 'cliente', 'vendedor', 'listaRebaja']);

            if (!$solicitud->cancelacion_solicitada_at) {
                abort(422, 'Esta solicitud no tiene una solicitud de cancelación pendiente.');
            }

            $estadoCancelada = CatalogoEstadoSolicitud::where('nombre', 'Cancelada')->firstOrFail();
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;

            if ($estadoAnteriorId == $estadoCancelada->id) {
                abort(422, 'Esta solicitud ya está cancelada.');
            }

            $this->conciliacion->liberar($solicitud);
            $snapshotDiff = [];
            $estadosConBeneficios = [
                CatalogoEstadoSolicitud::idDe('Respondida'),
                CatalogoEstadoSolicitud::idDe('Verificada'),
            ];

            if (
                in_array($estadoAnteriorId, $estadosConBeneficios)
                && $solicitud->proceso?->esFinanciero()
            ) {
                $otraSolicitudSostiene = $this->solicitudPosteriorSostieneBeneficio($solicitud);
                $listaRebaja = null;
                if (
                    ! $otraSolicitudSostiene
                    && $solicitud->catalogo_lista_rebaja_id
                    && $solicitud->cliente_id
                    && $this->validarListaInferior->exigeListaRebaja($solicitud)
                ) {
                    $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($solicitud->cliente_id);
                    if (
                        $cliente
                        && ! $cliente->lista_bloqueada
                        && ! app(\App\Services\Escalonamiento\EscalonamientoAutoridad::class)->clienteGobernadoPorModulo($cliente)
                    ) {
                        $listaRebaja = $this->validarListaInferior->validarListaInferior(
                            $solicitud,
                            (int) $solicitud->catalogo_lista_rebaja_id
                        );
                    }
                }

                $snapshotDiff = $this->ajustarMonto->revertirBeneficios($solicitud, Auth::id());

                if ($listaRebaja && $solicitud->cliente_id) {
                    $cliente = Cliente::with(['listaDescuento', 'vendedor', 'tipo'])->find($solicitud->cliente_id);
                    if (! $cliente) {
                        $listaRebaja = null;
                    }
                }

                if ($listaRebaja && $cliente && ! $this->conversionProtegeLaLista($cliente, $solicitud)) {
                    $cliente->lista_actual_id = $listaRebaja->id;
                    $cliente->save();
                    $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);
                    $snapshotDiff['despues'] = $this->ajustarMonto->capturarSnapshotCliente($cliente);
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

    private function conversionProtegeLaLista(Cliente $cliente, SolicitudTag $solicitud): bool
    {
        if ($cliente->modalidad_comercial === ClasificacionComercialCliente::MENUDEO) {
            return true;
        }

        return $cliente->modalidad_comercial === ClasificacionComercialCliente::MAYOREO
            && $cliente->modalidad_origen === ClasificacionComercialCliente::ORIGEN_CONVERSION
            && (int) $cliente->conversion_solicitud_id !== (int) $solicitud->id;
    }

    private function solicitudPosteriorSostieneBeneficio(SolicitudTag $solicitud): bool
    {
        if (! $solicitud->cliente_id) {
            return false;
        }

        $estados = array_values(array_filter([
            CatalogoEstadoSolicitud::idDe('Respondida'),
            CatalogoEstadoSolicitud::idDe('Verificada'),
        ]));

        if ($estados === []) {
            return false;
        }

        return SolicitudTag::query()
            ->where('cliente_id', $solicitud->cliente_id)
            ->where('id', '>', $solicitud->id)
            ->whereIn('catalogo_estado_solicitud_id', $estados)
            ->whereHas('proceso', function ($q) {
                $q->where(function ($q) {
                    $q->where('categoria_flujo', '!=', CatalogoProceso::CATEGORIA_OPERATIVO)
                        ->orWhereNull('categoria_flujo');
                });
            })
            ->exists();
    }
}
