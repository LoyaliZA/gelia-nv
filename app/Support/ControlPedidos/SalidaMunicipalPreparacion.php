<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use Illuminate\Validation\ValidationException;

final class SalidaMunicipalPreparacion
{
    public static function assertCaratulaColocada(PedidoBmaTareaPreparacion $tarea): PedidoBmaCaratula
    {
        $tarea->loadMissing('modalidad');
        if (! $tarea->modalidad?->esEnvioMunicipio()) {
            throw ValidationException::withMessages([
                'modalidad' => 'Esta acción aplica solo a envío a municipio.',
            ]);
        }

        if ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA) {
            throw ValidationException::withMessages([
                'estado' => 'Confirme la carátula colocada antes de liberar la salida.',
            ]);
        }

        $caratula = $tarea->caratulas()
            ->where('estado', PedidoBmaCaratula::ESTADO_COLOCADA)
            ->orderByDesc('version')
            ->first();

        if (! $caratula) {
            throw ValidationException::withMessages([
                'caratula' => 'Debe colocar la carátula vigente antes de continuar.',
            ]);
        }

        return $caratula;
    }
}
