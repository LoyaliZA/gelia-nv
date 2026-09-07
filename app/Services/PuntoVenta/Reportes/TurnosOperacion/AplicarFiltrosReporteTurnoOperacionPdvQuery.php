<?php

namespace App\Services\PuntoVenta\Reportes\TurnosOperacion;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\OperacionPdvEvento;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\OperacionPdvConfig;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class AplicarFiltrosReporteTurnoOperacionPdvQuery
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly OperacionPdvConfig $operacion,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function resolverAlcance(User $user, array $filtros): array
    {
        $tieneGlobal = $this->alcance->tieneAlcanceGlobal($user);
        $alcanceSolicitado = $filtros['alcance'];

        if ($alcanceSolicitado === MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL) {
            if (! $tieneGlobal) {
                throw new AuthorizationException('No autorizado para alcance global.');
            }
            $filtros['alcance_resuelto'] = MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL;
        } elseif ($alcanceSolicitado === MetricaTurnoOperacionPdvIds::ALCANCE_PROPIO) {
            if (
                $filtros['user_id'] !== null
                && (int) $filtros['user_id'] !== $user->id
            ) {
                throw new AuthorizationException('No autorizado para métricas personales de otra persona.');
            }

            $filtros['alcance_resuelto'] = MetricaTurnoOperacionPdvIds::ALCANCE_PROPIO;
            $filtros['user_id'] = $user->id;
        } elseif ($alcanceSolicitado === MetricaTurnoOperacionPdvIds::ALCANCE_EQUIPO) {
            $filtros['alcance_resuelto'] = MetricaTurnoOperacionPdvIds::ALCANCE_EQUIPO;
        } else {
            $filtros['alcance_resuelto'] = $tieneGlobal
                ? MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL
                : MetricaTurnoOperacionPdvIds::ALCANCE_EQUIPO;
        }

        $this->asegurarAcceso($user, $filtros);

        if (
            $filtros['user_id'] !== null
            && (int) $filtros['user_id'] !== $user->id
            && $filtros['alcance_resuelto'] === MetricaTurnoOperacionPdvIds::ALCANCE_PROPIO
        ) {
            throw new AuthorizationException('No autorizado para métricas personales de otra persona.');
        }

        if (
            $filtros['user_id'] !== null
            && (int) $filtros['user_id'] !== $user->id
            && $filtros['alcance_resuelto'] === MetricaTurnoOperacionPdvIds::ALCANCE_EQUIPO
        ) {
            $this->validarPersonaEnAlcanceEquipo($user, (int) $filtros['user_id'], $filtros);
        }

        if (! empty($filtros['sucursal_id'])) {
            $this->validarSucursal($user, (int) $filtros['sucursal_id'], $filtros['alcance_resuelto']);
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function asegurarAcceso(User $user, array $filtros): void
    {
        if (! $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_VER)) {
            throw new AuthorizationException('No autorizado para consultar métricas de turnos y operación.');
        }

        $alcance = $filtros['alcance_resuelto'] ?? MetricaTurnoOperacionPdvIds::ALCANCE_EQUIPO;

        if ($alcance === MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL) {
            $this->alcance->asegurarConsultaGlobal($user);

            return;
        }

        if (
            $this->alcance->idsSucursalesOperables($user)->isEmpty()
            && ! $this->alcance->tieneAlcanceGlobal($user)
        ) {
            throw new AuthorizationException('No autorizado: sin sucursales operables.');
        }
    }

    public function queryTurnos(User $user, array $filtros): Builder
    {
        $query = TurnoPdv::query();

        return $this->aplicarFiltrosTurno($query, $user, $filtros);
    }

    public function queryAtenciones(User $user, array $filtros): Builder
    {
        $query = TurnoPdvAtencion::query()
            ->whereHas('turno', fn (Builder $turno) => $this->aplicarFiltrosTurno($turno, $user, $filtros, false));

        return $this->aplicarFiltrosAtencion($query, $user, $filtros);
    }

    public function queryEventosTurno(User $user, array $filtros): Builder
    {
        $query = TurnoPdvEvento::query()
            ->whereHas('turno', fn (Builder $turno) => $this->aplicarFiltrosTurno($turno, $user, $filtros, false));

        return $query;
    }

    public function queryJornadas(User $user, array $filtros): Builder
    {
        $query = JornadaPdv::query();

        return $this->aplicarFiltrosJornada($query, $user, $filtros);
    }

    public function queryIntervalos(User $user, array $filtros): Builder
    {
        $query = IntervaloOperativoPdv::query()
            ->whereHas('jornada', fn (Builder $jornada) => $this->aplicarFiltrosJornada($jornada, $user, $filtros, false));

        return $this->aplicarFiltrosAtencion($query, $user, $filtros);
    }

    public function queryDiasSucursal(User $user, array $filtros): Builder
    {
        $query = SucursalDiaOperacionPdv::query();

        return $this->aplicarFiltrosSucursal($query, $user, $filtros);
    }

    public function queryEventosOperacion(User $user, array $filtros): Builder
    {
        $query = OperacionPdvEvento::query();

        return $this->aplicarFiltrosSucursal($query, $user, $filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function aplicarFiltrosTurno(Builder $query, User $user, array $filtros, bool $aplicarServicio = true): Builder
    {
        $this->aplicarFiltrosSucursal($query, $user, $filtros);

        if ($aplicarServicio && ! empty($filtros['servicio'])) {
            $query->where('servicio', (string) $filtros['servicio']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function aplicarFiltrosAtencion(Builder $query, User $user, array $filtros): Builder
    {
        if ($filtros['alcance_resuelto'] === MetricaTurnoOperacionPdvIds::ALCANCE_PROPIO) {
            $query->where('user_id', $user->id);
        } elseif (! empty($filtros['user_id'])) {
            $query->where('user_id', (int) $filtros['user_id']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function aplicarFiltrosJornada(Builder $query, User $user, array $filtros, bool $aplicarUsuario = true): Builder
    {
        $this->aplicarFiltrosSucursal($query, $user, $filtros);

        if ($aplicarUsuario) {
            $this->aplicarFiltrosAtencion($query, $user, $filtros);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function aplicarFiltrosSucursal(Builder $query, User $user, array $filtros): Builder
    {
        if (! empty($filtros['sucursal_id'])) {
            return $query->where('sucursal_id', (int) $filtros['sucursal_id']);
        }

        $alcance = $filtros['alcance_resuelto'];

        if ($alcance === MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL) {
            return $query->whereIn('sucursal_id', $this->alcance->idsSucursalesElegibles());
        }

        return $query->whereIn('sucursal_id', $this->alcance->idsSucursalesOperables($user));
    }

    /**
     * @return list<int>
     */
    public function idsSucursalesDesglose(User $user, array $filtros): array
    {
        if (! empty($filtros['sucursal_id'])) {
            return [(int) $filtros['sucursal_id']];
        }

        if ($filtros['alcance_resuelto'] === MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL) {
            return $this->alcance->idsSucursalesElegibles()->all();
        }

        return $this->alcance->idsSucursalesOperables($user)->all();
    }

    public function cumpleFechaOperativa(CarbonInterface $momento, int $sucursalId, array $filtros): bool
    {
        if (empty($filtros['fecha_operativa'])) {
            return true;
        }

        return $this->operacion->fechaOperativa($sucursalId, $momento) === $filtros['fecha_operativa'];
    }

    public function cumpleFranja(CarbonInterface $momento, int $sucursalId, array $filtros): bool
    {
        if ($filtros['franja_desde'] === null || $filtros['franja_hasta'] === null) {
            return true;
        }

        $zona = $this->operacion->zonaHorariaOperativa($sucursalId);
        $local = $momento->copy()->timezone($zona);
        $minutos = ((int) $local->format('H')) * 60 + (int) $local->format('i');

        [$desdeH, $desdeM] = array_map('intval', explode(':', $filtros['franja_desde']));
        [$hastaH, $hastaM] = array_map('intval', explode(':', $filtros['franja_hasta']));

        $desdeMinutos = $desdeH * 60 + $desdeM;
        $hastaMinutos = $hastaH * 60 + $hastaM;

        return $minutos >= $desdeMinutos && $minutos < $hastaMinutos;
    }

    public function cumpleAtribucion(
        CarbonInterface $momento,
        int $sucursalId,
        array $filtros,
        Carbon $desde,
        Carbon $hasta,
    ): bool {
        if ($momento->lt($desde) || $momento->gte($hasta)) {
            return false;
        }

        if (! $this->cumpleFechaOperativa($momento, $sucursalId, $filtros)) {
            return false;
        }

        return $this->cumpleFranja($momento, $sucursalId, $filtros);
    }

    private function validarSucursal(User $user, int $sucursalId, string $alcance): void
    {
        if ($alcance === MetricaTurnoOperacionPdvIds::ALCANCE_GLOBAL) {
            if (! $this->alcance->idsSucursalesElegibles()->contains($sucursalId)) {
                throw new AuthorizationException('Sucursal no autorizada.');
            }

            return;
        }

        if (! $this->alcance->idsSucursalesOperables($user)->contains($sucursalId)) {
            throw new AuthorizationException('Sucursal no autorizada.');
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function validarPersonaEnAlcanceEquipo(User $user, int $personaId, array $filtros): void
    {
        $sucursales = ! empty($filtros['sucursal_id'])
            ? collect([(int) $filtros['sucursal_id']])
            : $this->alcance->idsSucursalesOperables($user);

        $pertenece = User::query()
            ->whereKey($personaId)
            ->whereHas('sucursales', function (Builder $query) use ($sucursales): void {
                $query->whereIn('sucursales.id', $sucursales)
                    ->where('sucursales.activo', true)
                    ->where('sucursal_user.activo', true);
            })
            ->exists();

        if (! $pertenece) {
            throw new AuthorizationException('No autorizado para métricas personales de esa persona.');
        }
    }
}
