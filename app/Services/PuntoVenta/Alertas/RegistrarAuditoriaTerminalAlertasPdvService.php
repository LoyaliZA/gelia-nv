<?php

namespace App\Services\PuntoVenta\Alertas;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RegistrarAuditoriaTerminalAlertasPdvService
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
        ?int $designacionId = null,
    ): void {
        DB::table('pdv_terminal_alertas_auditoria')->insert([
            'designacion_id' => $designacionId,
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
