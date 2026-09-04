<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Illuminate\Database\Eloquent\Builder;

class ConsultaEquipoOperativoPdvService
{
    public function __construct(
        private readonly ConsultaPersonaDisponiblePdv $disponibilidad,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     nombre: string,
     *     jornada_estado: string|null,
     *     actividad: string|null,
     *     disponible: bool
     * }>
     */
    public function listar(int $sucursalId): array
    {
        $personas = User::query()
            ->permission([
                PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
                PuntoVentaModulo::PERMISO_TURNOS_VER,
            ])
            ->whereHas('sucursales', function (Builder $query) use ($sucursalId): void {
                $query->where('sucursales.id', $sucursalId)
                    ->where('sucursales.activo', true)
                    ->where('sucursal_user.activo', true);
            })
            ->orderBy('users.id')
            ->get(['users.id', 'users.name']);

        if ($personas->isEmpty()) {
            return [];
        }

        $userIds = $personas->pluck('id')->all();

        $jornadas = JornadaPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->whereIn('user_id', $userIds)
            ->whereIn('estado', [
                EstadoJornadaPdv::Abierta,
                EstadoJornadaPdv::CerradaConAtencion,
            ])
            ->get()
            ->keyBy('user_id');

        $intervalos = IntervaloOperativoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->whereIn('user_id', $userIds)
            ->whereNull('fin_at')
            ->get()
            ->keyBy('user_id');

        return $personas->map(function (User $persona) use ($sucursalId, $jornadas, $intervalos): array {
            $jornada = $jornadas->get($persona->id);
            $intervalo = $intervalos->get($persona->id);

            return [
                'id' => $persona->id,
                'nombre' => $persona->name,
                'jornada_estado' => $jornada?->estado?->value,
                'actividad' => $intervalo?->tipo?->value,
                'disponible' => $this->disponibilidad->esDisponible($persona, $sucursalId, false),
            ];
        })->values()->all();
    }
}
