<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\OperacionGestionAuditoriaPdv;
use App\Support\PuntoVenta\Operacion\EstadoVendedorOperacionPdv;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class RegistrarAuditoriaGestionOperativaPdvService
{
    public function registrar(
        int $actorId,
        int $userId,
        int $sucursalId,
        string $accion,
        EstadoVendedorOperacionPdv $estadoAnterior,
        EstadoVendedorOperacionPdv $estadoNuevo,
        CarbonInterface $ahora,
        ?string $idempotencyKey = null,
        ?array $contexto = null,
    ): OperacionGestionAuditoriaPdv {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existente = OperacionGestionAuditoriaPdv::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existente instanceof OperacionGestionAuditoriaPdv) {
                if ($existente->accion !== $accion
                    || $existente->user_id !== $userId
                    || $existente->sucursal_id !== $sucursalId) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
                    ]);
                }

                return $existente;
            }
        }

        return OperacionGestionAuditoriaPdv::query()->create([
            'actor_id' => $actorId,
            'user_id' => $userId,
            'sucursal_id' => $sucursalId,
            'accion' => $accion,
            'estado_anterior' => $estadoAnterior->value,
            'estado_nuevo' => $estadoNuevo->value,
            'contexto' => $contexto,
            'registrado_at' => $ahora,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
        ]);
    }
}
