<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\RegistrarPedidos\PoliticaAccionesRegistrarPedidos;
use Illuminate\Validation\ValidationException;

/** La captura inicial no asigna canal, cobertura ni una tarea. */
final class AplicarCapturaTiendaPedido
{
    public function __construct(
        private PoliticaAccionesRegistrarPedidos $politica,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(PedidoBma $pedido, array $datos, User $usuario): PedidoBma
    {
        $solicitud = $datos['solicitud_preparacion'] ?? null;
        if (! is_array($solicitud) || ! array_key_exists('atencion_tienda', $solicitud)) {
            return $pedido->fresh();
        }

        if (($solicitud['atencion_tienda'] ?? null) === 'prevista') {
            $atencion = $this->politica->evaluarAtencion([
                'cobertura' => PoliticaAccionesRegistrarPedidos::coberturaDeCodigo($solicitud['codigo_modalidad'] ?? null),
                'atencion' => 'prevista',
                'facultad' => $usuario->can(PoliticaAccionesRegistrarPedidos::PERMISO_MD) || $usuario->hasRole('Super Admin'),
                'sabado' => now()->isSaturday(),
            ]);
            if ($atencion['motivo']) {
                throw ValidationException::withMessages([
                    'atencion_tienda' => $atencion['motivo'],
                ]);
            }
        }

        return $pedido->fresh();
    }
}
