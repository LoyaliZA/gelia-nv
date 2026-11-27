<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Services\PuntoVenta\Resguardos\CrearRecepcionEsperadaPdvService;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\Nacional\GuiasPdfPorCaja;
use Illuminate\Support\Facades\Storage;

class MarcarEnviadoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private CrearRecepcionEsperadaPdvService $crearRecepcionEsperadaPdv,
    ) {}

    /**
     * @param  list<array{id?: int|string, numero_rastreo?: string|null}>|null  $cajasSeleccion
     */
    public function ejecutar(
        PedidoBma $pedido,
        int $usuarioId,
        ?array $cajasSeleccion = null,
        bool $todasLasCajasPendientes = false,
    ): PedidoBma {
        return DB::transaction(function () use ($pedido, $usuarioId, $cajasSeleccion, $todasLasCajasPendientes) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if ($todasLasCajasPendientes && (! GuiasPdfPorCaja::aplica($pedido) || $cajasSeleccion !== null)) {
                throw new \InvalidArgumentException('La salida de todas las cajas pendientes solo está disponible para envíos con guías por caja de Delegado y sin selección individual.');
            }

            if ($pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_ENVIADO) {
                return $pedido;
            }

            return $this->enviar($pedido, $usuarioId, $cajasSeleccion, $todasLasCajasPendientes);
        });
    }

    private function enviar(
        PedidoBma $pedido,
        int $usuarioId,
        ?array $cajasSeleccion,
        bool $todasLasCajasPendientes,
    ): PedidoBma {
        $this->assertCajaRecolectadaNoCambiaGuia($pedido, $cajasSeleccion);
        \App\Support\ControlPedidos\Nacional\BarreraRecepcionComplementoNacional::assert($pedido);
        \App\Support\ControlPedidos\Nacional\BarreraSeguroCajaNacional::assert($pedido);

        if ($pedido->estatus?->fase_ciclo !== CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO) {
            throw new \RuntimeException('El pedido no está pendiente de recolección o envío.');
        }

        if ($pedido->empacado_at === null) {
            throw new \RuntimeException('El pedido debe estar empacado antes de marcarlo como enviado.');
        }

        if ($pedido->consulta_actualizacion_pendiente) {
            throw new \RuntimeException('Hay una actualización de mercancía pendiente. CEDIS debe confirmarla antes de marcar el envío.');
        }

        \App\Support\ControlPedidos\PoliticaResguardoEnvio::assertPuedeDespachar($pedido);
        \App\Support\ControlPedidos\AtencionMismoDiaTienda::assertSalida($pedido);
        if (\App\Support\ControlPedidos\CustodiaDocumentalTienda::aplica($pedido)
            && ! app(MovimientosTiendaService::class)->tieneCustodiaDestino((int) $pedido->sucursal_destino_id)) {
            throw new \RuntimeException('Configure un almacén PDV activo de custodia en destino antes de confirmar la salida.');
        }

        if ($pedido->esTrasladoInternoAPdv()) {
            app(ValidarSucursalDestinoPedidoBma::class)->ejecutar($pedido, (int) $pedido->sucursal_destino_id, exigirSiAplica: true);
        }

        $pedido->loadMissing(['paqueteria', 'origen', 'cajas']);

        if (! $pedido->esTrasladoInternoAPdv() && $pedido->tienePaqueteriaPendiente()) {
            throw new \RuntimeException(
                'Actualice la paquetería (ya no use PAQ. PENDIENTE) antes de marcar el pedido como enviado.'
            );
        }

        \App\Support\ControlPedidos\Nacional\CoberturaEnvioNacional::assertDespacho($pedido);

        if ($pedido->ofreceRastreo() && empty($pedido->numero_rastreo)) {
            throw new \RuntimeException('El pedido requiere número de guía antes de marcarlo como enviado.');
        }

        \App\Support\ControlPedidos\Nacional\BarreraCreditoNacional::assertPuedeSalir($pedido);
        $porCaja = GuiasPdfPorCaja::aplica($pedido);
        if ($porCaja) {
            $pendientes = GuiasPdfPorCaja::pendientes($pedido);
            $confirmadas = GuiasPdfPorCaja::vigentes($pedido)->where('relacion_tipo', GuiasPdfPorCaja::CONFIRMADA)
                ->whereIn('pedido_bma_caja_id', $pendientes->pluck('id'));
            if ($pendientes->isEmpty() || GuiasPdfPorCaja::faltantes($pendientes, $confirmadas) > 0
                || $confirmadas->contains(fn ($doc) => ! Storage::disk($doc->discoArchivo())->exists($doc->ruta_archivo))) {
                throw new \RuntimeException('Delegado debe confirmar un PDF de guía vigente por cada caja pendiente.');
            }
        } else {
            $this->assertGuiaVigente($pedido, $cajasSeleccion);
        }

        $cajas = $pedido->cajas
            ->filter(fn (PedidoBmaCaja $c) => $c->estaActiva())
            ->sortBy('orden')
            ->values();
        $pendientes = $cajas->filter(fn (PedidoBmaCaja $c) => $c->estaPendiente())->values();
        $seleccion = is_array($cajasSeleccion) ? array_values($cajasSeleccion) : [];

        if (! $todasLasCajasPendientes && $pendientes->count() > 1 && $seleccion === []) {
            throw new \InvalidArgumentException('Selecciona qué envíos se recolectaron');
        }

        $idsMarcar = [];
        $rastreos = [];
        if ($seleccion === []) {
            $idsMarcar = $pendientes->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            foreach ($seleccion as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    throw new \InvalidArgumentException('Cada envío seleccionado debe indicar su id.');
                }
                $idsMarcar[] = $id;
                if (! $porCaja && isset($row['numero_rastreo']) && trim((string) $row['numero_rastreo']) !== '') {
                    $rastreos[$id] = trim((string) $row['numero_rastreo']);
                }
            }
        }

        $idsMarcar = array_values(array_unique($idsMarcar));
        $idsPedido = $cajas->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($idsMarcar as $id) {
            if (! in_array($id, $idsPedido, true)) {
                throw new \InvalidArgumentException('Uno o más envíos no pertenecen a este pedido.');
            }
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $idsMarcar, $rastreos, $cajas, $porCaja) {
            foreach ($idsMarcar as $id) {
                $caja = $cajas->firstWhere('id', $id) ?? PedidoBmaCaja::query()->find($id);
                if (! $caja || (int) $caja->pedido_bma_id !== (int) $pedido->id) {
                    throw new \InvalidArgumentException('Uno o más envíos no pertenecen a este pedido.');
                }
                if ($caja->estaRecolectada()) {
                    continue;
                }
                $attrs = [
                    'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_RECOLECTADA,
                    'recolectada_at' => now(),
                    'recolectada_por_id' => $usuarioId,
                ];
                if ($porCaja) {
                    $attrs['numero_rastreo'] = $pedido->numero_rastreo;
                } elseif (isset($rastreos[$id])) {
                    $attrs['numero_rastreo'] = $rastreos[$id];
                } elseif (! filled($caja->numero_rastreo) && filled($pedido->numero_rastreo)) {
                    $attrs['numero_rastreo'] = $pedido->numero_rastreo;
                }
                $caja->update($attrs);
            }

            $quedanPendientes = $pedido->cajas()->activas()->get()->contains(fn (PedidoBmaCaja $c) => $c->estaPendiente());

            if ($quedanPendientes) {
                return $pedido->fresh([
                    'cliente', 'estatus', 'documentos', 'almacen', 'cajas',
                    'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'vendedor',
                ]);
            }

            MaquinaEstadosPedidoBma::assertTransicion(
                $pedido->estatus?->fase_ciclo,
                CatalogoEstatusPedido::FASE_ENVIADO
            );
            $estatusEnviado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_ENVIADO);

            if (! $estatusEnviado) {
                throw new \RuntimeException('No se encontró el estatus ENVIADO.');
            }

            $estatusAnterior = $pedido->estatus;
            $municipal = \App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal::aplica($pedido) && $pedido->esTrasladoInternoAPdv();

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusEnviado->id,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusEnviado,
                $municipal ? 'Salida de CEDIS hacia PDV confirmada.' : ($pedido->esTrasladoInternoAPdv() ? 'Salida de traslado interno confirmada.' : 'Paquetería recogió el paquete.'),
                $municipal ? AccionesHistorialPedidoBma::SALIDA_CEDIS_PDV : AccionesHistorialPedidoBma::ENVIO_FINAL
            );

            $pedido = $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'almacen', 'cajas',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'vendedor',
                'origen', 'sucursalDestino', 'tareaPreparacionVigente.modalidad',
            ]);

            if ($pedido->requiereSucursalDestino() || $pedido->esTrasladoInternoAPdv()) {
                $this->crearRecepcionEsperadaPdv->ejecutar($pedido, $usuarioId);
            }

            $this->notificarService->ejecutar(
                $pedido,
                $municipal ? 'pedido_salida_cedis_pdv' : 'pedido_enviado',
                $municipal ? 'Salida de CEDIS hacia PDV' : ($pedido->esTrasladoInternoAPdv() ? 'Salida de traslado interno confirmada' : 'Paquetería recogió el paquete'),
                [],
                $usuarioId,
                true,
                ['url' => '/control-pedidos?tab=ENVIADOS&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
            );

            return $pedido;
        });
    }

    /** @param  list<array{id?: int|string, numero_rastreo?: string|null}>|null  $seleccion */
    private function assertCajaRecolectadaNoCambiaGuia(PedidoBma $pedido, ?array $seleccion): void
    {
        if (! is_array($seleccion)) {
            return;
        }
        $pedido->loadMissing('cajas');
        foreach ($seleccion as $row) {
            if (! is_array($row)) {
                continue;
            }
            $nuevo = trim((string) ($row['numero_rastreo'] ?? ''));
            if ($nuevo === '') {
                continue;
            }
            $caja = $pedido->cajas->firstWhere('id', (int) ($row['id'] ?? 0));
            if ($caja && $caja->estaRecolectada() && $nuevo !== trim((string) $caja->numero_rastreo)) {
                throw new \RuntimeException('Una caja recolectada no cambia de guía.');
            }
        }
    }

    /**
     * Una caja que sale tiene guía si ya la guardó, si llega en esta misma petición
     * o si el Delegado la capturó a nivel pedido. Solo se revisan las cajas que salen ahora.
     *
     * @param  list<array{id?: int|string, numero_rastreo?: string|null}>|null  $cajasSeleccion
     */
    private function assertGuiaVigente(PedidoBma $pedido, ?array $cajasSeleccion = null): void
    {
        $guias = $pedido->documentos()->where('tipo', \App\Models\ControlPedidos\PedidoBmaDocumento::TIPO_GUIA)->get();
        if ($guias->isNotEmpty() && $guias->where('activo', true)->isEmpty()) {
            throw new \RuntimeException('La guía invalidada no autoriza la salida. Se requiere una guía vigente.');
        }

        if (! \App\Support\ControlPedidos\Nacional\CapturaNacional::es($pedido)) {
            return;
        }

        $seleccion = collect($cajasSeleccion ?? [])->filter(fn ($fila) => is_array($fila));
        $saleAhora = $seleccion->pluck('id')->map(fn ($id) => (int) $id)->all();
        $guiaEnPeticion = $seleccion
            ->filter(fn (array $fila) => filled($fila['numero_rastreo'] ?? null))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $sinRastreo = $pedido->cajas
            ->filter(fn (PedidoBmaCaja $caja) => $caja->estaActiva() && $caja->estaPendiente())
            ->filter(fn (PedidoBmaCaja $caja) => $saleAhora === [] || in_array((int) $caja->id, $saleAhora, true))
            ->filter(fn (PedidoBmaCaja $caja) => ! filled($caja->numero_rastreo)
                && ! filled($pedido->numero_rastreo)
                && ! in_array((int) $caja->id, $guiaEnPeticion, true))
            ->isNotEmpty();
        if ($sinRastreo) {
            throw new \RuntimeException('Cada caja que sale requiere guía vigente.');
        }
    }
}
