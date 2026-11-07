<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\PuntoVenta\ResguardoPdv;

class EvaluarVencimientoApartadoFisicoService
{
    public function __construct(
        private SolicitarDevolucionCumplimientoService $devolucion,
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private NotificarPedidoBmaService $notificar,
        private CompromisoInventarioTienda $inventario,
        private CancelarCreditoSinRemisionTiendaService $cancelarCredito,
    ) {}

    public function ejecutar(): int
    {
        $filas = PedidoBmaCumplimientoFisico::query()
            ->with(['tarea.modalidad', 'tarea.pedido'])
            ->whereIn('estado', [
                PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR,
                PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO,
                PedidoBmaCumplimientoFisico::ESTADO_SEPARADA,
                PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA,
            ])
            ->whereNotNull('vence_at')
            ->where('vence_at', '<=', now())
            ->whereHas('tarea.modalidad', fn ($q) => $q->whereIn('codigo', CatalogoModalidadPreparacionPedido::CODIGOS_FASE4))
            ->orderBy('id')
            ->get();

        $movidas = 0;
        foreach ($filas as $cumplimiento) {
            $tarea = $cumplimiento->tarea;
            if (! $tarea || $tarea->estado === PedidoBmaTareaPreparacion::ESTADO_CANCELADA) {
                continue;
            }
            if (\App\Support\ControlPedidos\CustodiaDocumentalTienda::aplica($tarea->pedido)) {
                continue;
            }
            if ($this->mercanciaPagada($tarea->pedido)) {
                continue;
            }
            if ($tarea->modalidad?->codigo === CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA) {
                $clave = 'aviso-vence:'.$cumplimiento->id.':'.$cumplimiento->vence_at->toIso8601String();
                if (! \App\Models\ControlPedidos\PedidoBmaCumplimientoEvento::query()->where('idempotencia_clave', $clave)->exists()) {
                    $this->eventos->ejecutar($cumplimiento, \App\Models\ControlPedidos\PedidoBmaCumplimientoEvento::TIPO_VENCIMIENTO_AVISADO,
                        null, $clave, 'Terminó el plazo del apartado. Tienda debe revisar físicamente las piezas.');
                    $this->notificar->ejecutar($tarea->pedido, 'pedido_apartado_vencido',
                        'Terminó el plazo del apartado en PDV. Revise las piezas físicamente; el sistema no libera mercancía automáticamente.',
                        ['control_pedidos.tienda.ver'], null, true, ['url' => '/control-pedidos/tienda/tareas/'.$tarea->id]);
                    $movidas++;
                }
                continue;
            }
            if (! in_array($cumplimiento->estado, [PedidoBmaCumplimientoFisico::ESTADO_SEPARADA, PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA], true)) {
                continue;
            }
            $this->devolucion->ejecutar(
                $tarea,
                null,
                'Venció el plazo del apartado.',
                'vence:'.$cumplimiento->id.':'.$cumplimiento->vence_at?->toIso8601String(),
                null
            );
            $movidas++;
        }

        $movidas += $this->liberarPagadosPorRemision();
        $movidas += app(SeguimientoRecoleccionT02Service::class)->ejecutar();
        $movidas += $this->cancelarCredito->ejecutar();

        return $movidas;
    }

    private function liberarPagadosPorRemision(): int
    {
        $filas = PedidoBmaCumplimientoFisico::query()
            ->with(['tarea.modalidad', 'tarea.pedido.documentos'])
            ->whereNotIn('estado', [
                PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA,
                PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL,
                PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE,
            ])
            ->whereHas('tarea.modalidad', fn ($q) => $q->whereIn('codigo', [
                CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA,
                CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO,
            ]))
            ->orderBy('id')
            ->get();

        $movidas = 0;
        foreach ($filas as $cumplimiento) {
            $tarea = $cumplimiento->tarea;
            if (! $tarea || $tarea->estado === PedidoBmaTareaPreparacion::ESTADO_CANCELADA) {
                continue;
            }
            if (\App\Support\ControlPedidos\CustodiaDocumentalTienda::aplica($tarea->pedido)) {
                continue;
            }
            $antes = PedidoBmaCumplimientoEvento::query()
                ->where('pedido_bma_cumplimiento_fisico_id', $cumplimiento->id)
                ->where('idempotencia_clave', 'like', 'libera-20:%')
                ->count();
            $this->liberarFisicoPagado($cumplimiento, $tarea);
            $despues = PedidoBmaCumplimientoEvento::query()
                ->where('pedido_bma_cumplimiento_fisico_id', $cumplimiento->id)
                ->where('idempotencia_clave', 'like', 'libera-20:%')
                ->count();
            if ($despues > $antes || $cumplimiento->fresh()->estado === PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE) {
                $movidas++;
            }
        }

        return $movidas;
    }

    /**
     * 20 días naturales desde la remisión: pide el reintegro físico y no decide el dinero.
     */
    private function liberarFisicoPagado(PedidoBmaCumplimientoFisico $cumplimiento, PedidoBmaTareaPreparacion $tarea): void
    {
        $pedido = $tarea->pedido;
        if (! $pedido || $cumplimiento->estado === PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA) {
            return;
        }

        $remision = $pedido->documentos()
            ->where('tipo', PedidoBmaDocumento::TIPO_REMISION)
            ->orderBy('created_at')
            ->first();
        $desde = $remision?->created_at;
        if ($desde === null) {
            return;
        }
        if (now()->lt($desde->copy()->addDays(20))) {
            return;
        }

        $clave = 'libera-20:'.$cumplimiento->id.':'.$desde->toDateString();
        if (PedidoBmaCumplimientoEvento::query()->where('idempotencia_clave', $clave)->exists()) {
            return;
        }

        $motivo = 'Pasaron 20 días naturales desde la remisión. Reintegrar la mercancía a venta. El destino del dinero pagado no está definido y no se crea un saldo.';
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $json['liberacion_fisica_20'] = [
            'estado' => 'solicitada',
            'desde_remision' => $desde->toDateString(),
            'causa' => $motivo,
            'dinero' => 'pendiente_de_definicion',
        ];
        $pedido->update(['solicitud_preparacion_json' => $json]);
        $this->inventario->liberarPedido($pedido);
        $pedido->refresh();
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $json['liberacion_fisica_20']['reintegro'] = 'apartado';
        $pedido->update(['solicitud_preparacion_json' => $json]);
        ResguardoPdv::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->update([
                'entrega_bloqueada' => true,
                'vencido_repuesto_at' => now(),
            ]);

        if (\App\Support\ControlPedidos\MaquinaEstadosCumplimientoFisico::puedeTransicionar(
            $cumplimiento->estado,
            PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE
        )) {
            $this->devolucion->ejecutar($tarea, null, $motivo, $clave, null);

            return;
        }

        $this->eventos->ejecutar($cumplimiento, PedidoBmaCumplimientoEvento::TIPO_VENCIMIENTO_AVISADO, null, $clave, $motivo);
        $this->notificar->ejecutar(
            $pedido,
            'pedido_custodia_20_dias',
            $motivo,
            ['control_pedidos.tienda.ver'],
            null,
            true,
            ['url' => '/control-pedidos/tienda/tareas/'.$tarea->id]
        );
    }

    /**
     * La caducidad de un apartado impago no libera mercancía ya pagada o remisionada.
     * ponytail: la duración VIP sigue sin regla vigente. Los 20 días solo reintegran físicamente.
     */
    private function mercanciaPagada(?PedidoBma $pedido): bool
    {
        if ($pedido === null) {
            return false;
        }

        return $pedido->pago_validado_at !== null || filled($pedido->numero_remision);
    }
}
