<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaEnvioResguardo;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Services\ControlPedidos\RetirarCustodiaParaEnvioService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\ExclusividadRemisionEnvio;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class VincularRemisionPagadaService
{
    private const FASES_PROPIAS = [
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
        CatalogoEstatusPedido::FASE_PENDIENTE_GUIA_CLIENTE,
        CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        CatalogoEstatusPedido::FASE_EN_RUTA,
        CatalogoEstatusPedido::FASE_ENVIADO,
        CatalogoEstatusPedido::FASE_ENTREGADO,
        CatalogoEstatusPedido::FASE_CANCELADO,
    ];

    public function __construct(
        private RetirarCustodiaParaEnvioService $cedis,
        private RetirarCustodiaParaIntegracionLocalService $local,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function listar(PedidoBma $envio, string $q = ''): Collection
    {
        if (! ConsultaMunicipal::es($envio) || (int) $envio->cliente_id < 1) {
            return collect();
        }

        return PedidoBma::query()
            ->with('estatus:id,fase_ciclo,nombre_visual')
            ->where('cliente_id', $envio->cliente_id)
            ->where('id', '!=', $envio->id)
            ->whereNull('cancelado_at')
            ->whereNotNull('pago_validado_at')
            ->where(function ($query) {
                $query->where(function ($conNumero) {
                    $conNumero->whereNotNull('numero_remision')->where('numero_remision', '!=', '');
                })->orWhereHas('remision');
            })
            ->where(function ($query) use ($envio) {
                $query->where(function ($libre) {
                    $libre->whereNull('pedido_principal_id')->whereDoesntHave('envioQueLoAnexa');
                })->orWhere('pedido_principal_id', $envio->id)
                    ->orWhereHas('envioQueLoAnexa', fn ($q) => $q->where('pedido_envio_id', $envio->id));
            })
            ->whereDoesntHave('estatus', fn ($q) => $q->whereIn('fase_ciclo', self::FASES_PROPIAS))
            ->whereDoesntHave('resguardosPdv', fn ($q) => $q->where('estado', ResguardoPdv::ESTADO_ENTREGADO))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('folio', 'like', "%{$q}%")
                        ->orWhere('folio_remision', 'like', "%{$q}%")
                        ->orWhere('numero_remision', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'folio', 'folio_remision', 'numero_remision', 'cliente_id', 'total_mercancia', 'estatus_sucursal', 'solicitud_preparacion_json']);
    }

    public function ejecutar(PedidoBma $envio, PedidoBma $origen, User $usuario, string $mecanismo): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $envio)) {
            throw ValidationException::withMessages(['pedido' => 'No puede vincular remisiones en este envío.']);
        }
        if (! ConsultaMunicipal::es($envio)) {
            throw ValidationException::withMessages(['modalidad' => 'La vinculación de remisiones pagadas aplica al envío municipal.']);
        }
        if (! in_array($mecanismo, PoliticaResguardoEnvio::MECANISMOS, true)) {
            throw ValidationException::withMessages(['mecanismo' => 'Indique si la remisión se integra en sucursal, se traslada a CEDIS o se anexa al resguardo.']);
        }
        // Un solo orden de bloqueo (envío principal y luego la remisión) para vinculación, integración y liberación.
        return ExclusividadRemisionEnvio::conLockEnvio($envio, $origen, function ($bloqueados) use ($origen, $envio, $usuario, $mecanismo) {
            $origen = $bloqueados->get($origen->id);
            $envio = $bloqueados->get($envio->id);
            if ($this->yaVinculado($origen, $envio)) {
                // Reintento del mismo vínculo: idempotente mientras el envío no haya salido ni se haya liberado.
                PoliticaResguardoEnvio::assertIntegracionPermitida($envio);

                return $envio->fresh(['resguardosAnexados.resguardo', 'complementos']);
            }
            $this->assertEditable($envio);
            try {
                ExclusividadRemisionEnvio::assertDisponible($origen, $envio);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['remision' => $e->getMessage()]);
            }
            if (! $origen->tieneRemision() && ! filled($origen->numero_remision)) {
                throw ValidationException::withMessages(['remision' => 'La remisión pagada debe tener número o PDF.']);
            }
            if ($origen->pago_validado_at === null || PoliticaResguardoEnvio::incidenciaPagoMiembro($origen)) {
                throw ValidationException::withMessages(['pago' => 'Solo se vincula una remisión con pago auditado y vigente. No se cobra de nuevo.']);
            }
            $this->assertUbicacion($origen, $mecanismo);
            $enCustodia = ResguardoPdv::query()
                ->where('pedido_bma_id', $origen->id)
                ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
                ->exists();
            if ($mecanismo === PoliticaResguardoEnvio::MECANISMO_RESGUARDO && $enCustodia) {
                throw ValidationException::withMessages(['remision' => 'Esa remisión sigue en custodia de sucursal. Elija integrarla en sucursal o trasladarla a CEDIS.']);
            }

            if ($mecanismo === PoliticaResguardoEnvio::MECANISMO_CEDIS) {
                $this->cedis->ejecutar($origen, $envio, $usuario);
            } elseif ($mecanismo === PoliticaResguardoEnvio::MECANISMO_LOCAL) {
                $this->local->ejecutar($origen, $envio, $usuario);
            } else {
                PedidoBmaEnvioResguardo::query()->firstOrCreate([
                    'pedido_envio_id' => $envio->id,
                    'pedido_resguardo_id' => $origen->id,
                ]);
                if ($origen->catalogo_estatus_pedido_id) {
                    $this->historial->ejecutar(
                        $origen->id,
                        $usuario->id,
                        $origen->catalogo_estatus_pedido_id,
                        $origen->catalogo_estatus_pedido_id,
                        "Vinculada al envío {$envio->folio}. Falta confirmar su integración física en CEDIS. No se cobra de nuevo.",
                        AccionesHistorialPedidoBma::VINCULAR_REMISION_PAGADA
                    );
                }
            }
            $this->invalidarContinuacionSiAplica($envio);

            return $envio->fresh(['resguardosAnexados.resguardo', 'complementos']);
        });
    }

    public function desvincular(PedidoBma $envio, PedidoBma $origen, User $usuario): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $envio)) {
            throw ValidationException::withMessages(['pedido' => 'No puede desvincular remisiones de este envío.']);
        }

        return ExclusividadRemisionEnvio::conLockEnvio($envio, $origen, function ($bloqueados) use ($origen, $envio, $usuario) {
            $origen = $bloqueados->get($origen->id);
            $envio = $bloqueados->get($envio->id);
            $this->assertEditable($envio);
            $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
            if (! $this->yaVinculado($origen, $envio)) {
                throw ValidationException::withMessages(['remision' => 'Esa remisión no forma parte de este envío.']);
            }
            $estado = $json['integracion_local']['estado']
                ?? $json['retiro_recoleccion']['estado']
                ?? $json['integracion_envio']['estado']
                ?? 'anexado';
            if (! PoliticaResguardoEnvio::puedeDesvincular($envio, $origen, $estado)) {
                throw ValidationException::withMessages(['remision' => PoliticaResguardoEnvio::MENSAJE_IRREVERSIBLE]);
            }

            PedidoBmaEnvioResguardo::query()
                ->where('pedido_envio_id', $envio->id)
                ->where('pedido_resguardo_id', $origen->id)
                ->delete();

            if ((int) $origen->pedido_principal_id === (int) $envio->id) {
                $previo = $json['integracion_local']['bloqueo_previo'] ?? $json['retiro_recoleccion']['bloqueo_previo'] ?? [];
                unset($json['integracion_local'], $json['retiro_recoleccion']);
                $origen->update([
                    'pedido_principal_id' => null,
                    'solicitud_preparacion_json' => $json,
                ]);
                $this->restaurarCustodia($origen, is_array($previo) ? $previo : []);
            }
            $this->invalidarContinuacionSiAplica($envio);

            if ($origen->catalogo_estatus_pedido_id) {
                $this->historial->ejecutar(
                    $origen->id,
                    $usuario->id,
                    $origen->catalogo_estatus_pedido_id,
                    $origen->catalogo_estatus_pedido_id,
                    "Desvinculada del envío {$envio->folio}. La remisión vuelve a estar disponible.",
                    AccionesHistorialPedidoBma::DESVINCULAR_REMISION_PAGADA
                );
            }

            return $envio->fresh(['resguardosAnexados.resguardo', 'complementos']);
        });
    }

    /**
     * Devuelve cada custodia al bloqueo que tenía antes del vínculo. No libera una retención ajena
     * (cancelación, devolución, conversión): solo quita lo que puso este envío.
     *
     * @param  array<int|string, bool>  $previo
     */
    private function restaurarCustodia(PedidoBma $origen, array $previo): void
    {
        $resguardos = ResguardoPdv::query()
            ->where('pedido_bma_id', $origen->id)
            ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->get();
        foreach ($resguardos as $resguardo) {
            $otraRetencion = method_exists($origen, 'tieneCancelacionOperativaActiva') && $origen->tieneCancelacionOperativaActiva();
            $resguardo->update(['entrega_bloqueada' => $otraRetencion || (bool) ($previo[$resguardo->id] ?? false)]);
        }
    }

    /** Cierre de acumulación, salida física y liberación: una sola comprobación para vincular y desvincular. */
    private function assertEditable(PedidoBma $envio): void
    {
        try {
            PoliticaResguardoEnvio::assertConjuntoEditable($envio);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['conjunto' => $e->getMessage()]);
        }
    }

    /** La ubicación declarada para integrar debe corresponder con dónde está la mercancía. */
    private function assertUbicacion(PedidoBma $origen, string $mecanismo): void
    {
        $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
        $traslado = in_array($json['retiro_recoleccion']['estado'] ?? null, ['pendiente_traslado', 'en_traslado'], true);
        if ($mecanismo === PoliticaResguardoEnvio::MECANISMO_RESGUARDO && $traslado) {
            throw ValidationException::withMessages(['remision' => 'Esa remisión está en traslado. No se anexa como resguardada en CEDIS hasta recibirla.']);
        }
    }

    private function yaVinculado(PedidoBma $origen, PedidoBma $envio): bool
    {
        if ((int) $origen->pedido_principal_id === (int) $envio->id) {
            return true;
        }

        return PedidoBmaEnvioResguardo::query()
            ->where('pedido_envio_id', $envio->id)
            ->where('pedido_resguardo_id', $origen->id)
            ->exists();
    }

    private function invalidarContinuacionSiAplica(PedidoBma $envio): void
    {
        $envio->refresh();
        if (PoliticaResguardoEnvio::datos($envio)['continuacion_solicitada_at'] ?? null) {
            $envio->update(['solicitud_preparacion_json' => PoliticaResguardoEnvio::invalidarContinuacion($envio)]);
        }
    }
}
