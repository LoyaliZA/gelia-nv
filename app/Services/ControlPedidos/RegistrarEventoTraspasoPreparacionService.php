<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\SolicitudTraspaso;
use App\Models\User;

class RegistrarEventoTraspasoPreparacionService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
    ) {}

    public function salidaTienda(PedidoBmaTareaPreparacion $tarea, SolicitudTraspaso $solicitud, User $usuario): void
    {
        $this->registrar(
            $tarea,
            PedidoBmaCumplimientoEvento::TIPO_TRASPASO_SALIDA,
            $usuario,
            "traspaso:salida:{$solicitud->id}",
            'Salida física hacia CEDIS.',
            ['solicitud_traspaso_id' => $solicitud->id, 'folio' => $solicitud->folio]
        );
    }

    public function recibidoCedis(PedidoBmaTareaPreparacion $tarea, SolicitudTraspaso $solicitud, User $usuario): void
    {
        $this->registrar(
            $tarea,
            PedidoBmaCumplimientoEvento::TIPO_TRASPASO_RECIBIDO_CEDIS,
            $usuario,
            "traspaso:recibido:{$solicitud->id}",
            'CEDIS confirmó recepción.',
            ['solicitud_traspaso_id' => $solicitud->id, 'folio' => $solicitud->folio]
        );
    }

    public function rechazadoCedis(
        PedidoBmaTareaPreparacion $tarea,
        SolicitudTraspaso $solicitud,
        User $usuario,
        ?string $motivo,
    ): void {
        $this->registrar(
            $tarea,
            PedidoBmaCumplimientoEvento::TIPO_TRASPASO_RECHAZADO_CEDIS,
            $usuario,
            "traspaso:rechazado:{$solicitud->id}",
            $motivo ?: 'CEDIS rechazó el traspaso.',
            ['solicitud_traspaso_id' => $solicitud->id, 'folio' => $solicitud->folio, 'motivo' => $motivo]
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function registrar(
        PedidoBmaTareaPreparacion $tarea,
        string $tipo,
        User $usuario,
        string $idempotencia,
        ?string $motivo,
        array $datos,
    ): void {
        $cumplimiento = $this->asegurar->ejecutar($tarea);
        $this->eventos->ejecutar($cumplimiento, $tipo, $usuario, $idempotencia, $motivo, $datos);
    }
}
