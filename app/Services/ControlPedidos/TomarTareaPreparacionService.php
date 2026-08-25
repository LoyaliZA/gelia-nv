<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TomarTareaPreparacionService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicionService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $usuario, ?int $versionEsperada = null): PedidoBmaTareaPreparacion
    {
        if (! $usuario->can('control_pedidos.tienda.tomar')) {
            throw new \RuntimeException('No tiene permiso para tomar tareas de Tienda.');
        }

        return DB::transaction(function () use ($tarea, $usuario, $versionEsperada) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);

            if ($tarea->estado === PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION
                && (int) $tarea->asignada_a_id === (int) $usuario->id) {
                return $tarea;
            }

            if ($tarea->estado === PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION
                && $tarea->asignada_a_id
                && (int) $tarea->asignada_a_id !== (int) $usuario->id) {
                throw ValidationException::withMessages([
                    'tarea' => 'Esta tarea ya fue tomada por otro colaborador.',
                ]);
            }

            if ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_PENDIENTE) {
                throw ValidationException::withMessages([
                    'estado' => 'La tarea ya no está disponible para tomar.',
                ]);
            }

            if ($versionEsperada !== null && (int) $tarea->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó esta tarea. Actualice la página.',
                ]);
            }

            $tarea->update(['asignada_a_id' => $usuario->id]);

            return $this->transicionService->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
                $usuario->id,
                'tomar',
                'Tarea tomada por colaborador de Tienda.',
                null,
                $versionEsperada
            );
        });
    }
}
