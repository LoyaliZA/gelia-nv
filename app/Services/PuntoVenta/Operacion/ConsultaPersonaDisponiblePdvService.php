<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\User;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ConsultaPersonaDisponiblePdvService implements ConsultaPersonaDisponiblePdv
{
    public function __construct(
        private readonly OperacionPdvConfig $config,
        private readonly ConsultaVendedoresElegiblesPdvService $vendedoresElegibles,
    ) {}

    public function primeraDisponible(int $sucursalId, string $servicio): ?User
    {
        return $this->consultaBase($sucursalId)->first();
    }

    public function esDisponible(User $user, int $sucursalId, bool $paraAltaNueva = false): bool
    {
        return $this->consultaBase($sucursalId)
            ->whereKey($user->id)
            ->exists();
    }

    public function contarDisponibles(int $sucursalId, string $servicio): int
    {
        return (int) $this->consultaBase($sucursalId)->count();
    }

    /**
     * @return Builder<User>
     */
    private function consultaBase(int $sucursalId): Builder
    {
        if (! $this->sucursalAceptaAltas($sucursalId)) {
            return User::query()->whereRaw('1 = 0');
        }

        $inicioDia = $this->config->inicioDiaOperativo($sucursalId);
        $finDia = $inicioDia->copy()->endOfDay();
        $ahora = now();

        return $this->vendedoresElegibles->query($sucursalId)
            ->whereHas('jornadasPdv', function (Builder $query) use ($sucursalId, $inicioDia, $finDia, $ahora): void {
                $query->where('sucursal_id', $sucursalId)
                    ->where('estado', EstadoJornadaPdv::Abierta)
                    ->where('apertura_at', '>=', $inicioDia)
                    ->where('apertura_at', '<=', $finDia)
                    ->where(function (Builder $cooldown) use ($ahora): void {
                        $cooldown->whereNull('disponible_desde')
                            ->orWhere('disponible_desde', '<=', $ahora);
                    });
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

    public function sucursalAceptaAltas(int $sucursalId, ?CarbonInterface $ahora = null): bool
    {
        $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahora);
        $dia = SucursalDiaOperacionPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->whereDate('fecha_operativa', $fechaOperativa)
            ->first();

        return $dia instanceof SucursalDiaOperacionPdv && $dia->acepta_altas;
    }
}
