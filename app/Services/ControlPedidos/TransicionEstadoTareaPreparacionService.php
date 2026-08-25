<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaHistorial;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\MaquinaEstadosTareaPreparacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransicionEstadoTareaPreparacionService
{
    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        string $estadoNuevo,
        int $usuarioId,
        string $accion,
        ?string $comentario = null,
        ?array $meta = null,
        ?int $versionEsperada = null,
        ?User $actor = null,
        bool $omitirPermiso = false,
    ): PedidoBmaTareaPreparacion {
        return DB::transaction(function () use ($tarea, $estadoNuevo, $usuarioId, $accion, $comentario, $meta, $versionEsperada, $actor, $omitirPermiso) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
            $actor ??= User::find($usuarioId);

            if ($versionEsperada !== null && (int) $tarea->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otro usuario modificó esta tarea. Actualice la página e intente de nuevo.',
                ]);
            }

            $estadoAnterior = $tarea->estado;
            MaquinaEstadosTareaPreparacion::assertTransicion($estadoAnterior, $estadoNuevo);

            if (! $omitirPermiso) {
                $permiso = MaquinaEstadosTareaPreparacion::permisoParaDestino($estadoNuevo);
                if ($permiso && $actor && ! $actor->can($permiso)) {
                    throw ValidationException::withMessages([
                        'permiso' => 'No tiene permiso para esta acción.',
                    ]);
                }
            }

            if ($estadoAnterior === $estadoNuevo) {
                return $tarea;
            }

            $tarea->update([
                'estado' => $estadoNuevo,
                'version' => $tarea->version + 1,
            ]);

            PedidoBmaTareaHistorial::query()->create([
                'pedido_bma_tarea_preparacion_id' => $tarea->id,
                'usuario_id' => $usuarioId,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo,
                'accion' => $accion,
                'comentario' => $comentario,
                'meta_json' => $meta,
            ]);

            return $tarea->fresh();
        });
    }
}
