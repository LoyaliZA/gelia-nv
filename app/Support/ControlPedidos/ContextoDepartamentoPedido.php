<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ContextoDepartamentoPedido
{
    public static function dePedido(PedidoBma $pedido): ?int
    {
        if ($pedido->departamento_operacion_id) {
            return (int) $pedido->departamento_operacion_id;
        }
        $pedido->loadMissing('vendedor');

        return self::deUsuario($pedido->vendedor);
    }

    public static function deUsuario(?User $usuario): ?int
    {
        if (! $usuario) {
            return null;
        }
        if ($usuario->departamento_id) {
            return (int) $usuario->departamento_id;
        }
        $ids = $usuario->departamentos()->pluck('departamentos.id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    public static function validar(User $usuario, ?int $departamentoId): ?int
    {
        if (! $departamentoId) {
            return self::deUsuario($usuario);
        }
        if ($usuario->hasRole('Super Admin') || (int) $usuario->departamento_id === $departamentoId
            || $usuario->departamentos()->where('departamentos.id', $departamentoId)->exists()) {
            return $departamentoId;
        }
        throw ValidationException::withMessages(['departamento_operacion_id' => 'Seleccione uno de sus departamentos de trabajo.']);
    }
}
