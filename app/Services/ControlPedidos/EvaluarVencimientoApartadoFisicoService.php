<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;

class EvaluarVencimientoApartadoFisicoService
{
    public function __construct(
        private SolicitarDevolucionCumplimientoService $devolucion,
    ) {}

    public function ejecutar(): int
    {
        $filas = PedidoBmaCumplimientoFisico::query()
            ->with('tarea.modalidad')
            ->whereIn('estado', [
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
