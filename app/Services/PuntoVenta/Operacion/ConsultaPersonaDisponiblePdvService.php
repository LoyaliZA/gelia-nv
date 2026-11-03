<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Models\PuntoVenta\EsperaDisponibilidadPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ConsultaPersonaDisponiblePdvService implements ConsultaPersonaDisponiblePdv
{
    public function __construct(
        private readonly OperacionPdvConfig $config,
        private readonly ConsultaVendedoresElegiblesPdvService $vendedoresElegibles,
    ) {}

    public function primeraDisponible(int $sucursalId, string $servicio): ?User
    {
        $disponibles = $this->consultaBase($sucursalId)->get();
        if ($disponibles->isEmpty()) {
            return null;
        }

        $ultimosCierres = $this->ultimosCierresDelDia($sucursalId, $disponibles);
        $sinTurno = $disponibles
            ->filter(fn (User $user): bool => ! isset($ultimosCierres[(int) $user->id]))
            ->values();

        if ($sinTurno->isNotEmpty()) {
            return $sinTurno->random();
        }

        $jornadas = JornadaPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', EstadoJornadaPdv::Abierta)
            ->whereIn('user_id', $disponibles->modelKeys())
            ->get()
            ->keyBy(fn (JornadaPdv $jornada): int => (int) $jornada->user_id);

        return $disponibles
            ->sort(function (User $izquierda, User $derecha) use ($ultimosCierres, $jornadas): int {
                $momentoIzquierda = $this->momentoEnCola(
                    $ultimosCierres[(int) $izquierda->id],
                    $jornadas->get((int) $izquierda->id),
                );
                $momentoDerecha = $this->momentoEnCola(
                    $ultimosCierres[(int) $derecha->id],
                    $jornadas->get((int) $derecha->id),
                );
                $comparacion = $momentoIzquierda->getTimestamp() <=> $momentoDerecha->getTimestamp();

                return $comparacion !== 0 ? $comparacion : ((int) $izquierda->id <=> (int) $derecha->id);
            })
            ->first();
    }

    public function esDisponible(User $user, int $sucursalId, bool $paraAltaNueva = false): bool
    {
        return $this->consultaBase($sucursalId)
            ->whereKey($user->id)
            ->exists();
    }

    /**
     * Destino de reatención manual. La fila automática sigue usando esDisponible.
     * Durante la pausa abierta y la espera (fin de pausa o cierre de atención) la persona aún puede recibir esa reasignación.
     */
    public function esDestinoReatencionManual(User $user, int $sucursalId): bool
    {
        if ($this->esDisponible($user, $sucursalId)) {
            return true;
        }

        if ($this->consultaBase($sucursalId, exigirCooldownVencido: false)->whereKey($user->id)->exists()
            && $this->esperaVigentePermiteReatencionManual($user, $sucursalId)) {
            return true;
        }

        return $this->consultaBase($sucursalId, exigirCooldownVencido: false, excluirPausaAbierta: false)
            ->whereKey($user->id)
            ->whereHas('intervalosOperativosPdv', function (Builder $query) use ($sucursalId): void {
                $query->where('sucursal_id', $sucursalId)
                    ->whereNull('fin_at')
                    ->where('tipo', TipoIntervaloOperativoPdv::EnPausa);
            })
            ->exists();
    }

    public function contarDisponibles(int $sucursalId, string $servicio): int
    {
        return (int) $this->consultaBase($sucursalId)->count();
    }

    /**
     * @return Builder<User>
     */
    private function consultaBase(int $sucursalId, bool $exigirCooldownVencido = true, bool $excluirPausaAbierta = true): Builder
    {
        if (! $this->sucursalAceptaAltas($sucursalId)) {
            return User::query()->whereRaw('1 = 0');
        }

        $inicioDia = $this->config->inicioDiaOperativo($sucursalId);
        $finDia = $inicioDia->copy()->endOfDay();
        $ahora = now();

        $consulta = $this->vendedoresElegibles->query($sucursalId)
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
            });

        if ($excluirPausaAbierta) {
            $consulta->whereDoesntHave('intervalosOperativosPdv', function (Builder $query) use ($sucursalId): void {
                $query->where('sucursal_id', $sucursalId)
                    ->whereNull('fin_at')
                    ->where('tipo', TipoIntervaloOperativoPdv::EnPausa);
            });
        }

        return $consulta
            ->whereDoesntHave('atencionesTurnoPdv', function (Builder $query): void {
                $query->whereNull('fin_at');
            });
    }

    /**
     * @param  Collection<int, User>  $disponibles
     * @return array<int, CarbonInterface>
     */
    private function ultimosCierresDelDia(int $sucursalId, Collection $disponibles): array
    {
        $inicioDia = $this->config->inicioDiaOperativo($sucursalId);
        $cierres = [];

        $atenciones = TurnoPdvAtencion::query()
            ->whereIn('user_id', $disponibles->modelKeys())
            ->whereNotNull('fin_at')
            ->where('fin_at', '>=', $inicioDia)
            ->whereHas('turno', function (Builder $query) use ($sucursalId): void {
                $query->where('sucursal_id', $sucursalId);
            })
            ->orderByDesc('fin_at')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'fin_at']);

        foreach ($atenciones as $atencion) {
            $userId = (int) $atencion->user_id;
            if (! isset($cierres[$userId]) && $atencion->fin_at !== null) {
                $cierres[$userId] = $atencion->fin_at;
            }
        }

        return $cierres;
    }

    private function momentoEnCola(CarbonInterface $ultimoCierre, ?JornadaPdv $jornada): CarbonInterface
    {
        $disponibleDesde = $jornada?->disponible_desde;
        if ($disponibleDesde instanceof CarbonInterface && $disponibleDesde->greaterThan($ultimoCierre)) {
            return $disponibleDesde;
        }

        return $ultimoCierre;
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

    private function esperaVigentePermiteReatencionManual(User $user, int $sucursalId): bool
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
            && in_array($espera->origen, [
                EsperaDisponibilidadPdv::ORIGEN_PAUSA_FINALIZADA,
                EsperaDisponibilidadPdv::ORIGEN_ATENCION_CERRADA,
            ], true)
            && $espera->disponible_desde_nuevo !== null
            && $jornada->disponible_desde->equalTo($espera->disponible_desde_nuevo);
    }
}
