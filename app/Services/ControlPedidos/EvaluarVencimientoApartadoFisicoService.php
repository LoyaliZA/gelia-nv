<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;

class EvaluarVencimientoApartadoFisicoService
{
    public function __construct(
        private SolicitarDevolucionCumplimientoService $devolucion,
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private NotificarPedidoBmaService $notificar,
    ) {}

    public function ejecutar(): int
    {
        $filas = PedidoBmaCumplimientoFisico::query()
            ->with('tarea.modalidad')
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

        return $movidas;
    }
}
