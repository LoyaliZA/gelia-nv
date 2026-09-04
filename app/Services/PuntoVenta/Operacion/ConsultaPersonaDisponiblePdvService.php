<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Illuminate\Database\Eloquent\Builder;

class ConsultaPersonaDisponiblePdvService implements ConsultaPersonaDisponiblePdv
{
    public function __construct(
        private readonly OperacionPdvConfig $config,
    ) {}

    public function primeraDisponible(int $sucursalId, string $servicio): ?User
    {
        return $this->consultaBase($sucursalId, false)->first();
    }

    public function esDisponible(User $user, int $sucursalId, bool $paraAltaNueva = false): bool
    {
        return $this->consultaBase($sucursalId, $paraAltaNueva)
            ->whereKey($user->id)
            ->exists();
    }

    /**
     * @return Builder<User>
     */
    private function consultaBase(int $sucursalId, bool $paraAltaNueva): Builder
    {
        if ($paraAltaNueva && ! $this->sucursalAceptaAltas($sucursalId)) {
            return User::query()->whereRaw('1 = 0');
        }

        return User::query()
            ->permission([
                PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
                PuntoVentaModulo::PERMISO_TURNOS_VER,
            ])
            ->whereHas('sucursales', function (Builder $query) use ($sucursalId): void {
                $query->where('sucursales.id', $sucursalId)
                    ->where('sucursales.activo', true)
                    ->where('sucursal_user.activo', true);
            })
            ->whereHas('jornadasPdv', function (Builder $query) use ($sucursalId): void {
                $query->where('sucursal_id', $sucursalId)
                    ->where('estado', EstadoJornadaPdv::Abierta);
            })
            ->whereDoesntHave('intervalosOperativosPdv', function (Builder $query) use ($sucursalId): void {
                $query->where('sucursal_id', $sucursalId)
                    ->whereNull('fin_at')
                    ->where('tipo', TipoIntervaloOperativoPdv::EnPausa);
            })
            ->whereDoesntHave('atencionesTurnoPdv', function (Builder $query): void {
                $query->whereNull('fin_at');
            })
            ->orderBy('users.id');
    }

    private function sucursalAceptaAltas(int $sucursalId): bool
    {
        $fechaOperativa = $this->config->fechaOperativa($sucursalId);
        $dia = SucursalDiaOperacionPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->whereDate('fecha_operativa', $fechaOperativa)
            ->first();

        return ! $dia instanceof SucursalDiaOperacionPdv || $dia->acepta_altas;
    }
}
