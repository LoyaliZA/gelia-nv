<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Regeneración = generar con motivo obligatorio (invalida versión previa no colocada). */
class RegenerarCaratulaPedidoService
{
    public function __construct(
        private GenerarCaratulaPedidoService $generarService,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $motivo,
        ?int $versionEsperada = null,
    ): PedidoBmaCaratula {
        if (! $usuario->can('control_pedidos.tienda.regenerar_caratula')) {
            throw new \RuntimeException('No tiene permiso para regenerar carátula.');
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo_regeneracion' => 'El motivo de regeneración es obligatorio.',
            ]);
        }

        $colocada = $tarea->caratulas()
            ->where('estado', PedidoBmaCaratula::ESTADO_COLOCADA)
            ->exists();
        if ($colocada) {
            throw ValidationException::withMessages([
                'caratula' => 'No se regenera una carátula ya colocada. Corrija la tarea vía incidencia si aplica.',
            ]);
        }

        return $this->generarService->ejecutar($tarea, $usuario, $versionEsperada, $motivo);
    }
}
