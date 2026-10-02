<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosTareaPreparacion;
use Illuminate\Support\Facades\DB;

class InvalidarCaratulasVigentesService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicionService,
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        string $motivo,
        ?User $usuario = null,
    ): void {
        if (! $tarea->modalidad?->esEnvioMunicipio()) {
            return;
        }

        DB::transaction(function () use ($tarea, $motivo, $usuario) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
            $vigentes = $tarea->caratulas()
                ->whereIn('estado', [PedidoBmaCaratula::ESTADO_GENERADA, PedidoBmaCaratula::ESTADO_COLOCADA])
                ->get();

            if ($vigentes->isEmpty()) {
                return;
            }

            $habiaColocada = $vigentes->contains(fn ($c) => $c->estado === PedidoBmaCaratula::ESTADO_COLOCADA);

            foreach ($vigentes as $caratula) {
                $caratula->update(['estado' => PedidoBmaCaratula::ESTADO_INVALIDADA]);
            }

            if ($habiaColocada
                && $tarea->estado === PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA
                && MaquinaEstadosTareaPreparacion::puedeTransicionar(
                    $tarea->estado,
                    PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA
                )) {
                $actorId = (int) ($usuario?->id ?: $tarea->atendida_por_id ?: $tarea->solicitada_por_id ?: 0);
                $this->transicionService->ejecutar(
                    $tarea,
                    PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
                    $actorId,
                    'caratula_invalidada',
                    $motivo,
                    null,
                    null,
                    $usuario,
                    true
                );
            }

            $pedido = $tarea->pedido()->with('estatus')->first();
            if ($pedido?->estatus && $usuario) {
                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $pedido->estatus->id,
                    $pedido->estatus->id,
                    $motivo,
                    AccionesHistorialPedidoBma::CARATULA_REGENERADA
                );
            }
        });
    }
}
