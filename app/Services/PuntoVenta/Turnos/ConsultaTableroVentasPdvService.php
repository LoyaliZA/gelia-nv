<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Turnos\SerializadorTableroVentasPdv;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class ConsultaTableroVentasPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly PlazosTurnosPdvConfig $plazos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, CarbonInterface $ahora): array
    {
        if (! $this->alcance->permiteConsultaPiso($user, PuntoVentaModulo::PERMISO_TURNOS_VER)) {
            throw new AuthorizationException('No tienes permiso para consultar el tablero de ventas.');
        }

        $sucursalId = $this->alcance->sucursalActivaId($user);
        if ($sucursalId === null) {
            throw new AuthorizationException('Debes seleccionar una sucursal activa.');
        }

        $plazos = $this->plazos->obtener();
        $turnoAsignado = $this->consultarTurnoAsignado($user, $sucursalId);
        $colaContextual = $this->consultarColaContextual($user, $sucursalId);

        $payload = [
            'servidor_at' => $ahora->toIso8601String(),
            'plazos' => $plazos,
            'turno_asignado' => $turnoAsignado instanceof TurnoPdv
                ? SerializadorTableroVentasPdv::turno($turnoAsignado, $plazos, $ahora)
                : null,
            'cola_contextual' => $colaContextual
                ->map(fn (TurnoPdv $turno) => SerializadorTableroVentasPdv::turnoResumen($turno, $ahora))
                ->values()
                ->all(),
        ];

        if ($this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_TURNOS_TRANSFERIR)) {
            $payload['personas_transferencia'] = $this->consultarPersonasTransferencia($sucursalId, $user);
        }

        return $payload;
    }

    private function consultarTurnoAsignado(User $user, int $sucursalId): ?TurnoPdv
    {
        return TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', TurnoPdv::ESTADO_ASIGNADO)
            ->whereHas('atencionActual', static function ($query) use ($user): void {
                $query->where('user_id', $user->id)->whereNull('fin_at');
            })
            ->with(['atencionActual.prorroga'])
            ->first();
    }

    /**
     * @return Collection<int, TurnoPdv>
     */
    private function consultarColaContextual(User $user, int $sucursalId): Collection
    {
        return TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', TurnoPdv::ESTADO_EN_REATENCION)
            ->with(['atenciones' => static function ($query): void {
                $query->orderByDesc('id')->limit(1);
            }])
            ->orderByDesc('reatencion_expira_at')
            ->limit(20)
            ->get()
            ->filter(static function (TurnoPdv $turno) use ($user): bool {
                $ultima = $turno->atenciones->first();

                return $ultima instanceof TurnoPdvAtencion
                    && (int) $ultima->user_id === (int) $user->id;
            })
            ->take(5)
            ->values();
    }

    /**
     * @return list<array{id: int, primer_nombre: string}>
     */
    private function consultarPersonasTransferencia(int $sucursalId, User $actor): array
    {
        return User::query()
            ->whereHas('sucursales', static function ($query) use ($sucursalId): void {
                $query->where('sucursales.id', $sucursalId)
                    ->where('sucursales.activo', true)
                    ->where('sucursal_user.activo', true);
            })
            ->whereKeyNot($actor->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (User $persona): bool => $this->alcance->tienePermisoPdv(
                $persona,
                PuntoVentaModulo::PERMISO_TURNOS_CERRAR_ATENCION,
            ))
            ->map(static fn (User $persona): array => [
                'id' => $persona->id,
                'primer_nombre' => self::primerNombre($persona->name),
            ])
            ->values()
            ->all();
    }

    private static function primerNombre(?string $nombreCompleto): string
    {
        $partes = preg_split('/\s+/', trim((string) $nombreCompleto)) ?: [];

        return $partes[0] ?? '—';
    }
}
