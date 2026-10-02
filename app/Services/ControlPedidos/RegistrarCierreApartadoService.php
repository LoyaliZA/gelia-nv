<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;

class RegistrarCierreApartadoService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private SolicitarDevolucionCumplimientoService $devolucion,
    ) {}

    /**
     * Conserva documentos y eventos. Si hay mercancía apartada, pasa a devolución pendiente.
     */
    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        ?User $usuario,
        string $tipoEvento,
        string $motivo,
        string $idempotencia,
    ): PedidoBmaCumplimientoFisico {
        $cumplimiento = $this->asegurar->ejecutar($tarea);
        $cumplimiento->refresh();

        if ($cumplimiento->tieneMercanciaApartada()) {
            $this->devolucion->ejecutar(
                $tarea,
                $usuario,
                $motivo,
                $idempotencia.':devolucion',
                null
            );
            $cumplimiento->refresh();
        }

        $this->eventos->ejecutar(
            $cumplimiento,
            $tipoEvento,
            $usuario,
            $idempotencia,
            $motivo,
            ['inventario_sincronizado' => false]
        );

        return $cumplimiento->fresh();
    }

    public function porCancelacion(PedidoBmaTareaPreparacion $tarea, ?User $usuario, string $motivo, string $clave): void
    {
        $this->ejecutar(
            $tarea,
            $usuario,
            PedidoBmaCumplimientoEvento::TIPO_CANCELACION,
            $motivo,
            $clave
        );
    }
}
