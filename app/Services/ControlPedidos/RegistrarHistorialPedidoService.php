<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class RegistrarHistorialPedidoService
{
    /**
     * @param  array{ruta?: ?string, nombre?: ?string}|null  $evidencia
     */
    public function ejecutar(
        int $pedidoId,
        int $usuarioId,
        ?int $estatusAnteriorId,
        int $estatusNuevoId,
        ?string $comentarios = null,
        ?string $accion = null,
        ?array $evidencia = null,
    ): PedidoBmaHistorialEstado {
        [$rol, $departamento] = $this->snapshotActor($usuarioId);

        return PedidoBmaHistorialEstado::create([
            'pedido_bma_id' => $pedidoId,
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'rol' => $rol,
            'departamento' => $departamento,
            'estatus_anterior_id' => $estatusAnteriorId,
            'estatus_nuevo_id' => $estatusNuevoId,
            'comentarios' => $comentarios,
            'evidencia_ruta' => $evidencia['ruta'] ?? null,
            'evidencia_nombre' => $evidencia['nombre'] ?? null,
        ]);
    }

    public function registrarCreacion(int $pedidoId, int $usuarioId, int $estatusId): PedidoBmaHistorialEstado
    {
        return $this->ejecutar(
            $pedidoId,
            $usuarioId,
            null,
            $estatusId,
            'Pedido creado.',
            AccionesHistorialPedidoBma::CREACION_BORRADOR
        );
    }

    /**
     * @param  array{ruta?: ?string, nombre?: ?string}|null  $evidencia
     */
    public function registrarTransicion(
        int $pedidoId,
        int $usuarioId,
        CatalogoEstatusPedido $anterior,
        CatalogoEstatusPedido $nuevo,
        ?string $comentarios = null,
        ?string $accion = null,
        ?array $evidencia = null,
    ): PedidoBmaHistorialEstado {
        return $this->ejecutar(
            $pedidoId,
            $usuarioId,
            $anterior->id,
            $nuevo->id,
            $comentarios,
            $accion,
            $evidencia
        );
    }

    /** @return array{0: ?string, 1: ?string} */
    private function snapshotActor(int $usuarioId): array
    {
        $user = User::query()->with(['departamento', 'departamentos', 'roles'])->find($usuarioId);
        if (! $user) {
            return [null, null];
        }

        $roles = $user->getRoleNames();
        $rol = $roles->isEmpty()
            ? null
            : $roles->take(3)->implode(', ');

        $departamento = $user->departamentoParaBranding()?->nombre;

        return [$rol, $departamento];
    }
}
