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
            ->get(['id', 'folio', 'folio_remision', 'numero_remision', 'cliente_id', 'total_mercancia', 'estatus_sucursal']);
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
        PoliticaResguardoEnvio::assertConjuntoMutable($envio);

        $ya = $this->yaVinculado($origen, $envio);
        if ($ya) {
            return $envio->fresh(['resguardosAnexados.resguardo', 'complementos']);
        }

        if ($mecanismo === PoliticaResguardoEnvio::MECANISMO_CEDIS) {
            $this->cedis->ejecutar($origen, $envio, $usuario);
            $this->invalidarContinuacionSiAplica($envio);

            return $envio->fresh(['resguardosAnexados.resguardo', 'complementos']);
        }
        if ($mecanismo === PoliticaResguardoEnvio::MECANISMO_LOCAL) {
            $this->local->ejecutar($origen, $envio, $usuario);
            $this->invalidarContinuacionSiAplica($envio);

            return $envio->fresh(['resguardosAnexados.resguardo', 'complementos']);
        }

        return ExclusividadRemisionEnvio::conLock($origen, $envio, function ($bloqueados) use ($origen, $envio, $usuario) {
            $origen = $bloqueados->get($origen->id);
            $envio = $bloqueados->get($envio->id);
            PoliticaResguardoEnvio::assertConjuntoMutable($envio);
            try {
                ExclusividadRemisionEnvio::assertDisponible($origen, $envio);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['remision' => $e->getMessage()]);
            }
            if (! $origen->tieneRemision() && ! filled($origen->numero_remision)) {
                throw ValidationException::withMessages(['remision' => 'La remisión pagada debe tener número o PDF.']);
            }
            if ($origen->pago_validado_at === null) {
                throw ValidationException::withMessages(['pago' => 'Solo se vincula una remisión con cobertura verificada. No se cobra de nuevo.']);
            }

            PedidoBmaEnvioResguardo::query()->firstOrCreate([
                'pedido_envio_id' => $envio->id,
                'pedido_resguardo_id' => $origen->id,
            ]);
            $this->invalidarContinuacionSiAplica($envio);

            if ($origen->catalogo_estatus_pedido_id) {
                $this->historial->ejecutar(
                    $origen->id,
                    $usuario->id,
                    $origen->catalogo_estatus_pedido_id,
                    $origen->catalogo_estatus_pedido_id,
                    "Vinculada al envío {$envio->folio}. No se cobra de nuevo ni se activa el resguardo por este vínculo.",
                    AccionesHistorialPedidoBma::VINCULAR_REMISION_PAGADA
                );
            }

            return $envio->fresh(['resguardosAnexados.resguardo', 'complementos']);
        });
    }

    public function desvincular(PedidoBma $envio, PedidoBma $origen, User $usuario): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $envio)) {
            throw ValidationException::withMessages(['pedido' => 'No puede desvincular remisiones de este envío.']);
        }
        PoliticaResguardoEnvio::assertConjuntoMutable($envio);

        return ExclusividadRemisionEnvio::conLock($origen, $envio, function ($bloqueados) use ($origen, $envio, $usuario) {
            $origen = $bloqueados->get($origen->id);
            $envio = $bloqueados->get($envio->id);
            $json = is_array($origen->solicitud_preparacion_json) ? $origen->solicitud_preparacion_json : [];
            $estado = $json['integracion_local']['estado'] ?? $json['retiro_recoleccion']['estado'] ?? 'anexado';
            if (! PoliticaResguardoEnvio::puedeDesvincular($envio, $origen, $estado)) {
                throw ValidationException::withMessages(['remision' => PoliticaResguardoEnvio::MENSAJE_IRREVERSIBLE]);
            }

            PedidoBmaEnvioResguardo::query()
                ->where('pedido_envio_id', $envio->id)
                ->where('pedido_resguardo_id', $origen->id)
                ->delete();

            if ((int) $origen->pedido_principal_id === (int) $envio->id) {
                unset($json['integracion_local'], $json['retiro_recoleccion']);
                $origen->update([
                    'pedido_principal_id' => null,
                    'solicitud_preparacion_json' => $json,
                ]);
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
