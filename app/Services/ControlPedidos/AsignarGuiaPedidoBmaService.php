<?php

namespace App\Services\ControlPedidos;

use App\Events\PedidoBmaActualizado;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\ControlPedidos\Nacional\GuiasPdfPorCaja;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;

class AsignarGuiaPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
        private AvanzarColaErroresPedidoBmaService $colaErroresService,
    ) {}

    public function ejecutar(PedidoBma $pedido, string $numeroRastreo, int $usuarioId, bool $registrarImpresionCedis = false): PedidoBma
    {
        $guia = trim($numeroRastreo);

        if (\App\Support\ControlPedidos\Nacional\CapturaNacional::es($pedido)) {
            return DB::transaction(fn () => $this->asignar(
                PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id), $guia, $usuarioId, $registrarImpresionCedis
            ));
        }

        return $this->asignar($pedido, $guia, $usuarioId, $registrarImpresionCedis);
    }

    private function asignar(PedidoBma $pedido, string $guia, int $usuarioId, bool $registrarImpresionCedis): PedidoBma
    {

        if ($guia === '') {
            throw new \InvalidArgumentException('El número de guía es obligatorio.');
        }

        \App\Support\ControlPedidos\Nacional\BarreraRecepcionComplementoNacional::assert($pedido);
        \App\Support\ControlPedidos\Nacional\BarreraSeguroCajaNacional::assert($pedido);

        $pedido->loadMissing(['estatus', 'paqueteria', 'origen']);
        InstruccionEntrega::assertGuiaNoPosterior($pedido);

        \App\Support\ControlPedidos\PoliticaResguardoEnvio::assertPuedeDespachar($pedido);

        if ($pedido->es_resguardo) {
            throw new \RuntimeException('Un pedido en resguardo no puede recibir guía. Libere el resguardo primero.');
        }

        app(ResguardosEnvioPedido::class)->assertNoSalePorSuCuenta($pedido);

        if ($pedido->tienePaqueteriaPendiente()) {
            throw new \RuntimeException(
                'Actualice la paquetería (ya no use PAQ. PENDIENTE) antes de asignar guía.'
            );
        }

        \App\Support\ControlPedidos\Nacional\BarreraCreditoNacional::assertPuedeGuiar($pedido);
        \App\Support\ControlPedidos\Nacional\CoberturaEnvioNacional::assertDespacho($pedido);

        $this->assertCajasConPeso($pedido);

        $porCaja = GuiasPdfPorCaja::aplica($pedido);
        $fase = $pedido->estatus?->fase_ciclo;
        $puedeConfirmar = $porCaja && in_array($fase, [
            CatalogoEstatusPedido::FASE_EN_CEDIS,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO,
        ], true);

        if (! $puedeConfirmar && ! $pedido->puedeAsignarGuia()) {
            throw new \RuntimeException('El pedido no está pendiente de guía.');
        }

        $yaEmpacado = $fase === CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA
            || $pedido->empacado_at !== null;

        return DB::transaction(function () use ($pedido, $guia, $usuarioId, $yaEmpacado, $porCaja, $registrarImpresionCedis) {
            $documentosConfirmados = $porCaja ? $this->confirmarPdfs($pedido, $guia) : null;
            if ($porCaja && $documentosConfirmados === null) {
                return $pedido;
            }

            $estatusAnterior = $pedido->estatus;
            $rastreoAnterior = $pedido->numero_rastreo;
            $corrigeRastreo = $porCaja && filled($rastreoAnterior) && $rastreoAnterior !== $guia
                && $estatusAnterior?->fase_ciclo === CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO;
            $attrsCorreccion = $corrigeRastreo ? [
                'guia_retraso' => true,
                'guia_corregida_at' => now(),
                'guia_corregida_por_id' => $usuarioId,
            ] : [];

            $attrsCola = $this->attrsTrasCorregirGuia($pedido, $porCaja ? ['numero_rastreo', 'guia_pdf'] : ['numero_rastreo'], $usuarioId);

            if ($yaEmpacado) {
                $faseDestino = MaquinaEstadosPedidoBma::faseDestinoTrasAsignarGuia();
                MaquinaEstadosPedidoBma::assertTransicion($estatusAnterior?->fase_ciclo, $faseDestino);
                $estatusPendienteEnvio = CatalogoEstatusPedido::porFase($faseDestino);

                if (!$estatusPendienteEnvio) {
                    throw new \RuntimeException('No se encontró el estatus PENDIENTE_DE_ENVIO.');
                }

                $pedido->update(array_merge([
                    'numero_rastreo' => $guia,
                    'guia_subida_at' => now(),
                    'catalogo_estatus_pedido_id' => $estatusPendienteEnvio->id,
                ], $attrsCola, $attrsCorreccion));

                if ($porCaja && ! $pedido->wasChanged()) {
                    PedidoBmaActualizado::dispatch($pedido->id, 'actualizado');
                }

                $this->historialService->registrarTransicion(
                    $pedido->id,
                    $usuarioId,
                    $estatusAnterior,
                    $estatusPendienteEnvio,
                    $corrigeRastreo
                        ? "Guía de rastreo corregida (provoca retraso): {$rastreoAnterior} → {$guia}"
                        : "Guía de rastreo asignada: {$guia}. Pendiente de recolección.",
                    $corrigeRastreo ? AccionesHistorialPedidoBma::ACTUALIZAR_GUIA : AccionesHistorialPedidoBma::ASIGNACION_GUIA
                );

                $this->registrarImpresion($pedido, $usuarioId, $documentosConfirmados, $registrarImpresionCedis);
                $pedido = $pedido->fresh(['cliente', 'paqueteria', 'estatus', 'vendedor', 'documentos', 'origen']);

                $this->notificarService->ejecutar(
                    $pedido,
                    $corrigeRastreo ? 'pedido_guia_retraso' : 'pedido_guia_asignada',
                    $corrigeRastreo
                        ? "La guía del pedido fue corregida y provoca un retraso. Anterior: {$rastreoAnterior}. Nueva: {$guia}."
                        : "Guía de rastreo asignada: {$guia}",
                    $corrigeRastreo ? ['control_pedidos.cedis', 'control_pedidos.auditar'] : ['control_pedidos.cedis'],
                    $usuarioId,
                    false,
                    ['url' => '/control-pedidos/cedis?tab=PENDIENTES_ENVIO&q='.urlencode((string) ($pedido->folio_remision ?: $pedido->folio ?: $pedido->id))]
                );

                return $pedido;
            }

            $pedido->update(array_merge([
                'numero_rastreo' => $guia,
                'guia_subida_at' => now(),
            ], $attrsCola));

            if ($porCaja && ! $pedido->wasChanged()) {
                PedidoBmaActualizado::dispatch($pedido->id, 'actualizado');
            }

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusAnterior->id,
                $estatusAnterior->id,
                "Guía de rastreo asignada (pendiente de empaque): {$guia}",
                AccionesHistorialPedidoBma::ASIGNACION_GUIA
            );

            $this->registrarImpresion($pedido, $usuarioId, $documentosConfirmados, $registrarImpresionCedis);
            return $pedido->fresh(['cliente', 'paqueteria', 'estatus', 'vendedor', 'documentos', 'origen']);
        });
    }

    private function confirmarPdfs(PedidoBma $pedido, string $rastreo): ?Collection
    {
        $pendientes = GuiasPdfPorCaja::pendientes($pedido);
        $guias = GuiasPdfPorCaja::vigentes($pedido)->whereIn('pedido_bma_caja_id', $pendientes->pluck('id'));
        if ($pendientes->isEmpty() || GuiasPdfPorCaja::faltantes($pendientes, $guias) > 0
            || $guias->contains(fn ($doc) => ! Storage::disk($doc->discoArchivo())->exists($doc->ruta_archivo))) {
            throw new \RuntimeException('Adjunte un PDF de guía vigente por cada caja pendiente antes de asignar.');
        }

        $avances = $guias->where('relacion_tipo', GuiasPdfPorCaja::AVANCE);
        if ($rastreo !== $pedido->numero_rastreo && filled($pedido->numero_rastreo)) {
            if ($pedido->guiaSoloLecturaHastaEmpaque()) {
                throw new \RuntimeException('El rastreo asignado queda fijo hasta que se confirme el empaque.');
            }
            if ($pedido->cajas->contains(fn ($caja) => $caja->estaRecolectada())) {
                throw new \RuntimeException('El rastreo compartido queda fijo después de recolectar una caja.');
            }
            if ($avances->count() !== $guias->count()) {
                throw new \RuntimeException('Adjunte los PDFs correspondientes al nuevo rastreo antes de asignarlo.');
            }
        }
        if ($avances->isEmpty() && $rastreo === $pedido->numero_rastreo) {
            return null;
        }

        $pedido->documentos()->whereIn('id', $avances->pluck('id'))
            ->update(['relacion_tipo' => GuiasPdfPorCaja::CONFIRMADA]);

        return $avances;
    }

    private function registrarImpresion(PedidoBma $pedido, int $usuarioId, ?Collection $documentos, bool $solicitada): void
    {
        if (! $solicitada) {
            return;
        }

        $registro = app(RegistrarGuiasComunicadasCedis::class);
        if ($documentos !== null) {
            $registro->porCaja($pedido, $usuarioId, $documentos);
        } else {
            $registro->general($pedido, $usuarioId);
        }
    }

    /** @param  list<string>  $camposResueltos */
    private function attrsTrasCorregirGuia(PedidoBma $pedido, array $camposResueltos, int $usuarioId): array
    {
        if (empty($pedido->campos_incorrectos)) {
            return [];
        }

        $restantes = $this->colaErroresService->quitarCampos(
            $pedido,
            $camposResueltos,
            $usuarioId,
            'Número de guía corregido'
        );

        return $restantes === []
            ? $this->colaErroresService->attrsColaVacia()
            : $this->colaErroresService->attrsColaPendiente($restantes);
    }

    private function assertCajasConPeso(PedidoBma $pedido): void
    {
        if (! \App\Support\ControlPedidos\Nacional\CapturaNacional::es($pedido)) {
            return;
        }
        $pedido->loadMissing('cajas');
        $sinPeso = $pedido->cajas->contains(
            fn (\App\Models\ControlPedidos\PedidoBmaCaja $caja) => $caja->estaActiva() && $caja->peso_real_kg === null
        );
        if ($sinPeso) {
            throw new \RuntimeException('Hay una caja sin peso. No se asigna guía.');
        }
    }
}
