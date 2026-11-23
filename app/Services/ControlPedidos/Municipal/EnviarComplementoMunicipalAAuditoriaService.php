<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\Municipal\ComplementosMunicipales;
use App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * M-03b: envía las piezas adicionales a la revisión del auxiliar con su propio pago. No pide consulta, pesaje ni
 * logística: el complemento no se transporta por sí mismo; su integración física la confirma el área que tiene el paquete.
 * El auxiliar valida pago y registra la remisión con las operaciones de siempre.
 */
final class EnviarComplementoMunicipalAAuditoriaService
{
    public function __construct(
        private RegistrarPagoPedidoBmaService $pagos,
        private RegistrarHistorialPedidoService $historial,
        private NotificarPedidoBmaService $notificar,
    ) {}

    public function ejecutar(PedidoBma $complemento, User $usuario): PedidoBma
    {
        return DB::transaction(function () use ($complemento, $usuario) {
            $complemento = PedidoBma::query()->lockForUpdate()->findOrFail($complemento->id);
            if (! ComplementosMunicipales::es($complemento) || ComplementosMunicipales::estado($complemento) !== ComplementosMunicipales::ESTADO_ABIERTO) {
                throw ValidationException::withMessages(['complemento' => 'Ese pedido no es una pieza adicional abierta.']);
            }
            if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $complemento)) {
                throw ValidationException::withMessages(['permiso' => 'Quien registró el pedido envía las piezas adicionales.']);
            }
            if (! $complemento->esEditablePorVendedora()) {
                throw ValidationException::withMessages(['complemento' => 'Solo se envía un pedido en borrador o rechazado.']);
            }
            $principal = PedidoBma::query()->lockForUpdate()->findOrFail($complemento->pedido_principal_id);
            if (RecorridoFisicoMunicipal::envioYaSalioParaAjustes($principal)) {
                throw ValidationException::withMessages(['complemento' => RecorridoFisicoMunicipal::MENSAJE_ANEXO_SALIO]);
            }
            if (! filled($complemento->folio_remision)) {
                throw ValidationException::withMessages(['folio_remision' => 'Falta el folio del pedido de las piezas adicionales.']);
            }
            if ((float) $complemento->total_mercancia <= 0) {
                throw ValidationException::withMessages(['total_mercancia' => 'Falta el importe de las piezas adicionales.']);
            }
            if (! $complemento->documentos()->exists()) {
                throw ValidationException::withMessages(['evidencias' => 'Falta el documento de las piezas adicionales.']);
            }
            try {
                $this->pagos->assertCubiertoParaEnviar($complemento);
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                throw ValidationException::withMessages(['pago' => $e->getMessage()]);
            }

            $anterior = $complemento->estatus;
            $nuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR)
                ?? CatalogoEstatusPedido::porCodigo('AZUL_1');
            if (! $nuevo) {
                throw new \RuntimeException('No se encontró el estatus PENDIENTE_AUXILIAR.');
            }
            MaquinaEstadosPedidoBma::assertTransicion($anterior?->fase_ciclo, CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR);
            $complemento->update(['catalogo_estatus_pedido_id' => $nuevo->id]);
            $this->historial->registrarTransicion(
                $complemento->id,
                $usuario->id,
                $anterior,
                $nuevo,
                "Piezas adicionales del envío {$principal->folio} enviadas a revisión del auxiliar.",
                AccionesHistorialPedidoBma::ENVIO_AUXILIAR
            );
            $this->notificar->ejecutar(
                $complemento->fresh(),
                'pedido_pendiente_auxiliar',
                "Piezas adicionales del envío {$principal->folio} pendientes de pago y remisión.",
                ['control_pedidos.auditar'],
                $usuario->id,
                false,
                ['url' => '/control-pedidos/auditar?tab=PENDIENTES&q='.urlencode((string) $complemento->folio)]
            );

            return $complemento->fresh(['estatus', 'documentos']);
        });
    }
}
