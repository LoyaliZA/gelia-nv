<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Models\PuntoVenta\EsperaDisponibilidadPdv;
use App\Models\PuntoVenta\JornadaPdv;
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

    /**
     * Destino de reatención manual. La fila automática sigue usando esDisponible.
     * Durante la espera de fin de pausa la persona aún puede recibir esa reasignación.
     */
    public function esDestinoReatencionManual(User $user, int $sucursalId): bool
    {
        if ($this->esDisponible($user, $sucursalId)) {
            return true;
        }

        if (! $this->consultaBase($sucursalId, exigirCooldownVencido: false)->whereKey($user->id)->exists()) {
            return false;
        }

        return $this->esperaVigenteEsFinDePausa($user, $sucursalId);
    }

    public function contarDisponibles(int $sucursalId, string $servicio): int
    {
        return (int) $this->consultaBase($sucursalId)->count();
    }

    /**
     * @return Builder<User>
     */
    private function consultaBase(int $sucursalId, bool $exigirCooldownVencido = true): Builder
    {
        if (! $this->sucursalAceptaAltas($sucursalId)) {
            return User::query()->whereRaw('1 = 0');
        }

        $inicioDia = $this->config->inicioDiaOperativo($sucursalId);
        $finDia = $inicioDia->copy()->endOfDay();
        $ahora = now();

        return $this->vendedoresElegibles->query($sucursalId)
            ->whereHas('jornadasPdv', function (Builder $query) use ($sucursalId, $inicioDia, $finDia, $ahora, $exigirCooldownVencido): void {
                $query->where('sucursal_id', $sucursalId)
                    ->where('estado', EstadoJornadaPdv::Abierta)
                    ->where('apertura_at', '>=', $inicioDia)
                    ->where('apertura_at', '<=', $finDia);

                if ($exigirCooldownVencido) {
                    $query->where(function (Builder $cooldown) use ($ahora): void {
                        $cooldown->whereNull('disponible_desde')
                            ->orWhere('disponible_desde', '<=', $ahora);
                    });
                }
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

    private function esperaVigenteEsFinDePausa(User $user, int $sucursalId): bool
    {
        $jornada = JornadaPdv::query()
            ->where('user_id', $user->id)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', EstadoJornadaPdv::Abierta)
            ->first();

        if (! $jornada instanceof JornadaPdv || $jornada->disponible_desde === null || $jornada->disponible_desde->lte(now())) {
            return false;
        }

        $espera = EsperaDisponibilidadPdv::query()
            ->where('jornada_id', $jornada->id)
            ->orderByDesc('id')
            ->first();

        return $espera instanceof EsperaDisponibilidadPdv
            && $espera->origen === EsperaDisponibilidadPdv::ORIGEN_PAUSA_FINALIZADA
            && $espera->disponible_desde_nuevo !== null
            && $jornada->disponible_desde->equalTo($espera->disponible_desde_nuevo);
    }
}
