<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\EquipoAsistenciaDiaPdv;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use App\Support\PuntoVenta\Operacion\EstadoVendedorOperacionPdv;
use App\Support\PuntoVenta\Turnos\SerializadorTableroVentasPdv;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ConsultaEquipoOperativoPdvService
{
    public function __construct(
        private readonly OperacionPdvConfig $config,
        private readonly ResolverEstadoVendedorOperacionPdvService $resolverEstado,
        private readonly ConsultaVendedoresElegiblesPdvService $vendedoresElegibles,
        private readonly PlazosTurnosPdvConfig $plazos,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(int $sucursalId, ?CarbonInterface $ahora = null): array
    {
        $ahora = $ahora ?? now();
        $fechaOperativa = $this->config->fechaOperativa($sucursalId, $ahora);

        $personas = $this->vendedoresElegibles->query($sucursalId)
            ->get(['users.id', 'users.name', 'users.foto_perfil']);

        if ($personas->isEmpty()) {
            return [];
        }

        $userIds = $personas->pluck('id')->all();

        $jornadas = JornadaPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->whereIn('user_id', $userIds)
            ->where(function (Builder $query) use ($fechaOperativa): void {
                $query->whereIn('estado', [
                    EstadoJornadaPdv::Abierta,
                    EstadoJornadaPdv::CerradaConAtencion,
                ])->orWhere(function (Builder $cerrada) use ($fechaOperativa): void {
                    $cerrada->where('estado', EstadoJornadaPdv::Cerrada)
                        ->whereDate('apertura_at', $fechaOperativa);
                });
            })
            ->get()
            ->groupBy('user_id')
            ->map(fn ($grupo) => $grupo->sortByDesc('id')->first());

        $intervalos = IntervaloOperativoPdv::query()
            ->with('motivoPausa:id,nombre,slug')
            ->where('sucursal_id', $sucursalId)
            ->whereIn('user_id', $userIds)
            ->whereNull('fin_at')
            ->get()
            ->keyBy('user_id');

        $asistencias = EquipoAsistenciaDiaPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->whereIn('user_id', $userIds)
            ->whereDate('fecha_operativa', $fechaOperativa)
            ->get()
            ->keyBy('user_id');

        $plazos = $this->plazos->obtenerOPredeterminado();

        $atencionesAbiertas = TurnoPdvAtencion::query()
            ->with([
                'turno:id,folio,snapshot_nombre_llamado,snapshot_cliente_nombre,servicio',
                'prorroga',
            ])
            ->whereIn('user_id', $userIds)
            ->whereNull('fin_at')
            ->get()
            ->keyBy('user_id');

        return $personas->map(function (User $persona) use (
            $sucursalId,
            $jornadas,
            $intervalos,
            $asistencias,
            $atencionesAbiertas,
            $ahora,
            $plazos,
        ): array {
            $jornada = $jornadas->get($persona->id);
            $intervalo = $intervalos->get($persona->id);
            $asistencia = $asistencias->get($persona->id);
            $atencionAbierta = $atencionesAbiertas->get($persona->id);
            $tieneAtencion = $atencionAbierta !== null;

            $estado = $this->resolverEstado->resolver(
                $sucursalId,
                $jornada,
                $intervalo,
                $tieneAtencion,
                $asistencia,
                $ahora,
            );

            return [
                'id' => $persona->id,
                'nombre' => $persona->name,
                'foto_perfil' => $persona->foto_perfil,
                'estado_vendedor' => $estado->value,
                'actividad' => $intervalo?->tipo?->value,
                'recibe_turnos' => $estado->recibeTurnos(),
                'pausa_motivo' => $estado === EstadoVendedorOperacionPdv::EnRetencion
                    ? $intervalo?->textoMotivoPausaCompleto()
                    : null,
                'atencion_actual' => $this->serializarAtencionActual($atencionAbierta, $plazos, $ahora),
                'jornada' => $jornada instanceof JornadaPdv ? [
                    'estado' => $jornada->estado->value,
                    'version' => $jornada->version,
                    'apertura_at' => $jornada->apertura_at?->toIso8601String(),
                    'cierre_at' => $jornada->cierre_at?->toIso8601String(),
                ] : null,
                'intervalo' => $intervalo instanceof IntervaloOperativoPdv ? [
                    'tipo' => $intervalo->tipo?->value,
                    'inicio_at' => $intervalo->inicio_at?->toIso8601String(),
                    'motivo' => $intervalo->etiquetaMotivoPausa(),
                ] : null,
                'cronometro' => $this->resolverEstado->serializarCronometro($estado, $jornada, $intervalo),
                'acciones' => $this->resolverEstado->accionesDisponibles($estado),
            ];
        })->values()->all();
    }

    /**
     * @param  array{
     *   espera_inicial_minutos: int,
     *   prorroga_minutos: int,
     *   ventana_reatencion_minutos: int,
     *   aviso_tolerancia_espera_minutos?: int,
     *   aviso_tolerancia_prorroga_minutos?: int
     * }  $plazos
     * @return array<string, mixed>|null
     */
    private function serializarAtencionActual(?TurnoPdvAtencion $atencion, array $plazos, CarbonInterface $ahora): ?array
    {
        if ($atencion === null) {
            return null;
        }

        $turno = $atencion->turno;
        if ($turno === null) {
            return null;
        }

        $cliente = $turno->snapshot_cliente_nombre ?: $turno->snapshot_nombre_llamado;

        return array_merge(
            SerializadorTableroVentasPdv::atencion($atencion, $plazos, $ahora),
            [
                'folio' => (string) $turno->folio,
                'cliente' => $cliente !== null && $cliente !== '' ? (string) $cliente : null,
                'servicio' => $turno->servicio !== null ? (string) $turno->servicio : null,
            ],
        );
    }
}
