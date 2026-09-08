<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\EquipoAsistenciaDiaPdv;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\OperacionPdvConfig;
use App\Services\PuntoVenta\Operacion\ResolverEstadoVendedorOperacionPdvService;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\EstadoVendedorOperacionPdv;
use App\Support\PuntoVenta\Turnos\SerializadorTableroVentasPdv;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;

class ConsultaTableroVentasPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly PlazosTurnosPdvConfig $plazos,
        private readonly OperacionPdvConfig $operacionConfig,
        private readonly ResolverEstadoVendedorOperacionPdvService $resolverEstadoVendedor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(User $user, CarbonInterface $ahora): array
    {
        if (! $this->alcance->permiteConsultaPiso($user, PuntoVentaModulo::PERMISO_TURNOS_ATENDER)) {
            throw new AuthorizationException('No tienes permiso para consultar tu atención.');
        }

        $sucursalId = $this->alcance->sucursalActivaId($user);
        if ($sucursalId === null) {
            throw new AuthorizationException('Debes seleccionar una sucursal activa.');
        }

        $plazos = $this->plazos->obtener();
        $turnoAsignado = $this->consultarTurnoAsignado($user, $sucursalId);
        $atencionPrevia = $turnoAsignado instanceof TurnoPdv
            ? $this->consultarAtencionPrevia($turnoAsignado)
            : null;
        $estadoPropio = $this->resolverEstadoPropio($user, $sucursalId, $ahora);

        $payload = [
            'servidor_at' => $ahora->toIso8601String(),
            'plazos' => $plazos,
            'turno_asignado' => $turnoAsignado instanceof TurnoPdv
                ? SerializadorTableroVentasPdv::turno($turnoAsignado, $plazos, $ahora, $atencionPrevia)
                : null,
            'estado_vendedor' => $estadoPropio['estado_vendedor'],
            'cronometro' => $estadoPropio['cronometro'],
            'pausa_motivo' => $estadoPropio['pausa_motivo'],
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

    private function consultarAtencionPrevia(TurnoPdv $turno): ?TurnoPdvAtencion
    {
        $atencionActual = $turno->relationLoaded('atencionActual')
            ? $turno->atencionActual
            : null;

        if (! $atencionActual instanceof TurnoPdvAtencion || $atencionActual->numero_secuencia <= 1) {
            return null;
        }

        return TurnoPdvAtencion::query()
            ->where('turno_id', $turno->id)
            ->where('numero_secuencia', $atencionActual->numero_secuencia - 1)
            ->whereNotNull('fin_at')
            ->first();
    }

    /**
     * @return array{estado_vendedor: string|null, cronometro: array<string, mixed>|null, pausa_motivo: string|null}
     */
    private function resolverEstadoPropio(User $user, int $sucursalId, CarbonInterface $ahora): array
    {
        $fechaOperativa = $this->operacionConfig->fechaOperativa($sucursalId, $ahora);

        $jornada = JornadaPdv::query()
            ->where('user_id', $user->id)
            ->where('sucursal_id', $sucursalId)
            ->where(function ($query) use ($fechaOperativa): void {
                $query->whereIn('estado', [
                    EstadoJornadaPdv::Abierta,
                    EstadoJornadaPdv::CerradaConAtencion,
                ])->orWhere(function ($cerrada) use ($fechaOperativa): void {
                    $cerrada->where('estado', EstadoJornadaPdv::Cerrada)
                        ->whereDate('apertura_at', $fechaOperativa);
                });
            })
            ->latest('id')
            ->first();

        $intervalo = IntervaloOperativoPdv::query()
            ->with('motivoPausa:id,nombre,slug')
            ->where('user_id', $user->id)
            ->where('sucursal_id', $sucursalId)
            ->whereNull('fin_at')
            ->first();

        $asistencia = EquipoAsistenciaDiaPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('user_id', $user->id)
            ->whereDate('fecha_operativa', $fechaOperativa)
            ->first();

        $tieneAtencion = TurnoPdvAtencion::query()
            ->where('user_id', $user->id)
            ->whereNull('fin_at')
            ->exists();

        $estadoVendedor = $this->resolverEstadoVendedor->resolver(
            $sucursalId,
            $jornada,
            $intervalo,
            $tieneAtencion,
            $asistencia,
            $ahora,
        );

        return [
            'estado_vendedor' => $estadoVendedor->value,
            'cronometro' => $this->resolverEstadoVendedor->serializarCronometro($estadoVendedor, $jornada, $intervalo),
            'pausa_motivo' => $estadoVendedor === EstadoVendedorOperacionPdv::EnRetencion
                ? $intervalo?->textoMotivoPausaCompleto()
                : null,
        ];
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
                PuntoVentaModulo::PERMISO_TURNOS_ATENDER,
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
