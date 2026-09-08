<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\ConsultaVendedoresElegiblesPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Turnos\SerializadorBandejaReatencionPdv;
use Carbon\CarbonInterface;

class ConsultaBandejaReatencionPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ConsultaVendedoresElegiblesPdvService $vendedoresElegibles,
        private readonly ConsultaPersonaDisponiblePdv $consultaDisponible,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(User $actor, CarbonInterface $ahora): array
    {
        if (! $this->alcance->tienePermisoPdv($actor, PuntoVentaModulo::PERMISO_TURNOS_REATENCION_ASIGNAR)) {
            return [];
        }

        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            return [];
        }

        $turnos = TurnoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('servicio', TurnoPdv::SERVICIO_VENTAS)
            ->where('estado', TurnoPdv::ESTADO_EN_REATENCION)
            ->whereNull('atencion_actual_id')
            ->where('reatencion_expira_at', '>', $ahora)
            ->orderBy('reatencion_expira_at')
            ->orderBy('id')
            ->get();

        if ($turnos->isEmpty()) {
            return [];
        }

        $atencionesPrevias = TurnoPdvAtencion::query()
            ->with('user')
            ->whereIn('turno_id', $turnos->pluck('id'))
            ->whereNotNull('fin_at')
            ->orderByDesc('numero_secuencia')
            ->get()
            ->groupBy('turno_id')
            ->map(static fn ($grupo) => $grupo->first());

        $candidatosBase = $this->listarCandidatosDisponibles($sucursalId);

        return $turnos
            ->map(function (TurnoPdv $turno) use ($atencionesPrevias, $candidatosBase, $ahora): array {
                $atencionPrevia = $atencionesPrevias->get($turno->id);
                $excluirId = $atencionPrevia instanceof TurnoPdvAtencion
                    ? (int) $atencionPrevia->user_id
                    : null;

                $candidatos = array_values(array_filter(
                    $candidatosBase,
                    static fn (array $candidato): bool => $excluirId === null || $candidato['id'] !== $excluirId,
                ));

                return SerializadorBandejaReatencionPdv::turno(
                    $turno,
                    $atencionPrevia instanceof TurnoPdvAtencion ? $atencionPrevia : null,
                    $ahora,
                    $candidatos,
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    private function listarCandidatosDisponibles(int $sucursalId): array
    {
        return $this->vendedoresElegibles
            ->query($sucursalId)
            ->get()
            ->filter(fn (User $user): bool => $this->consultaDisponible->esDisponible(
                $user,
                $sucursalId,
            ))
            ->map(static fn (User $user): array => SerializadorBandejaReatencionPdv::candidato($user))
            ->values()
            ->all();
    }
}
