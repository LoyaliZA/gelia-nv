<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\User;
use Illuminate\Database\QueryException;

class RegistrarEventoCumplimientoFisicoService
{
    /**
     * @param  array<string, mixed>|null  $datos
     */
    public function ejecutar(
        PedidoBmaCumplimientoFisico $cumplimiento,
        string $tipo,
        ?User $usuario,
        string $idempotencia,
        ?string $motivo = null,
        ?array $datos = null,
    ): PedidoBmaCumplimientoEvento {
        $existente = PedidoBmaCumplimientoEvento::query()
            ->where('idempotencia_clave', $idempotencia)
            ->first();
        if ($existente) {
            return $existente;
        }

        try {
            return PedidoBmaCumplimientoEvento::query()->create([
                'pedido_bma_cumplimiento_fisico_id' => $cumplimiento->id,
                'pedido_bma_tarea_preparacion_id' => $cumplimiento->pedido_bma_tarea_preparacion_id,
                'tipo' => $tipo,
                'usuario_id' => $usuario?->id,
                'motivo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null,
                'idempotencia_clave' => $idempotencia,
                'datos' => $datos,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            $repetido = PedidoBmaCumplimientoEvento::query()
                ->where('idempotencia_clave', $idempotencia)
                ->first();
            if ($repetido) {
                return $repetido;
            }
            throw $e;
        }
    }
}
