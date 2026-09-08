<?php

namespace App\Support\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use Carbon\CarbonInterface;

final class SerializadorBandejaReatencionPdv
{
    /**
     * @param  list<array{id: int, nombre: string}>  $candidatos
     * @return array<string, mixed>
     */
    public static function turno(
        TurnoPdv $turno,
        ?TurnoPdvAtencion $atencionPrevia,
        CarbonInterface $ahora,
        array $candidatos,
    ): array {
        $vendedorAnterior = $atencionPrevia?->relationLoaded('user')
            ? $atencionPrevia->user
            : null;

        $expiraAt = $turno->reatencion_expira_at;
        $restanteSegundos = $expiraAt !== null
            ? max(0, (int) $ahora->diffInSeconds($expiraAt, false))
            : 0;

        return [
            'id' => $turno->id,
            'folio' => $turno->folio,
            'version' => $turno->version,
            'cliente_nombre' => $turno->snapshot_nombre_llamado
                ?? $turno->snapshot_cliente_nombre
                ?? '—',
            'atencion_previa' => $atencionPrevia instanceof TurnoPdvAtencion ? [
                'id' => $atencionPrevia->id,
                'numero_secuencia' => $atencionPrevia->numero_secuencia,
                'cierre_at' => $atencionPrevia->fin_at?->toIso8601String(),
                'motivo_cierre' => $atencionPrevia->motivo_cierre,
            ] : null,
            'vendedor_anterior' => $vendedorAnterior instanceof User ? [
                'id' => $vendedorAnterior->id,
                'nombre' => $vendedorAnterior->name,
            ] : null,
            'reatencion_expira_at' => $expiraAt?->toIso8601String(),
            'restante_segundos' => $restanteSegundos,
            'candidatos' => $candidatos,
        ];
    }

    /**
     * @return array{id: int, nombre: string}
     */
    public static function candidato(User $user): array
    {
        return [
            'id' => $user->id,
            'nombre' => $user->name,
        ];
    }
}
