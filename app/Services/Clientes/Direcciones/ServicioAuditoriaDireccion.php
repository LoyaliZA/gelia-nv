<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\ClienteDireccion;
use App\Models\ClienteDireccionAuditoria;

class ServicioAuditoriaDireccion
{
    /**
     * @param  array<string, mixed>|null  $anteriores
     * @param  array<string, mixed>|null  $nuevos
     */
    public function ejecutar(
        int $clienteId,
        string $accion,
        ?int $usuarioId = null,
        ?int $direccionId = null,
        ?int $solicitudId = null,
        ?array $anteriores = null,
        ?array $nuevos = null,
        ?string $origen = null,
    ): ClienteDireccionAuditoria {
        return ClienteDireccionAuditoria::query()->create([
            'cliente_id' => $clienteId,
            'cliente_direccion_id' => $direccionId,
            'solicitud_direccion_id' => $solicitudId,
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'origen' => $origen,
            'datos_anteriores' => $anteriores,
            'datos_nuevos' => $nuevos,
        ]);
    }
}
