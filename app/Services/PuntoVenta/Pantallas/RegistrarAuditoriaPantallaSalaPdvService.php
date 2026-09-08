<?php

namespace App\Services\PuntoVenta\Pantallas;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RegistrarAuditoriaPantallaSalaPdvService
{
    /**
     * @param  array<string, mixed>  $contexto
     */
    public function registrar(
        int $sucursalId,
        ?int $userId,
        string $tipo,
        CarbonInterface $ahora,
        array $contexto = [],
        ?int $tokenId = null,
    ): void {
        DB::table('pdv_pantalla_sala_auditoria')->insert([
            'token_id' => $tokenId,
            'sucursal_id' => $sucursalId,
            'user_id' => $userId,
            'tipo' => $tipo,
            'contexto' => json_encode($contexto),
            'ocurrido_at' => $ahora,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
}
