<?php

namespace App\Services\ControlPedidos\Nacional\Multienvio;

use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Support\ControlPedidos\Nacional\CapturaNacional;

/**
 * Solo lectura: indica si un pedido nacional califica para Multienvío.
 */
class EvaluarMultienvioNacional
{
    /**
     * @return array{elegible: bool, motivo: ?string, n_cajas: int, revision: string, estado: ?string, multienvio_id: ?int}
     */
    public function ejecutar(PedidoBma $pedido): array
    {
        $cajas = $pedido->cajas()->activas()->get(['uuid_operativo', 'peso_cobrado_kg']);
        $nCajas = $cajas->count();

        $grupo = $pedido->multienvio_nacional_id
            ? MultienvioNacional::query()->find($pedido->multienvio_nacional_id)
            : MultienvioNacional::query()->where('pedido_origen_id', $pedido->id)->first();

        // Un miembro (id de grupo ajeno) nunca puede ser origen de otro grupo; el origen de un grupo
        // ya confirmado queda en estado terminal y se explica por su estado, no por el número de cajas.
        $esMiembro = $pedido->multienvio_nacional_id !== null && (int) $grupo?->pedido_origen_id !== (int) $pedido->id;

        $motivo = match (true) {
            ! CapturaNacional::es($pedido) => 'El pedido no es un envío nacional ordinario.',
            $pedido->esComplemento() => 'Los complementos no forman Multienvío.',
            ! $pedido->consultaCerrada() => 'La consulta aún no está cerrada.',
            $esMiembro => 'El pedido ya es un envío de un multienvío.',
            $grupo?->estado === MultienvioNacional::ESTADO_ACTIVO => 'El multienvío ya está activo.',
            $grupo?->estado === MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE => 'El multienvío está pendiente de regularización.',
            $nCajas < 2 => 'Se necesitan al menos dos cajas activas.',
            default => null,
        };

        return [
            'elegible' => $motivo === null,
            'motivo' => $motivo,
            'n_cajas' => $nCajas,
            'revision' => $this->revision($cajas->all()),
            'estado' => $grupo?->estado,
            'multienvio_id' => $grupo?->id,
        ];
    }

    /** @param  array<int, PedidoBmaCaja>  $cajas */
    private function revision(array $cajas): string
    {
        $huellas = array_map(
            fn (PedidoBmaCaja $c) => $c->uuid_operativo.':'.number_format((float) $c->peso_cobrado_kg, 3, '.', ''),
            $cajas
        );
        sort($huellas);

        return sha1(implode('|', $huellas));
    }
}
