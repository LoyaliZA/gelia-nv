<?php

namespace App\Services\PuntoVenta\Reportes;

use App\Models\PuntoVenta\EsperaDisponibilidadPdv;
use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\User;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ConsultarLineaTiempoAuditoriaOperacionPdvService
{
    /**
     * @param  array{sucursal_id: int, user_id: int, desde: CarbonInterface, hasta: CarbonInterface}  $filtros
     * @return list<array{
     *   id: string,
     *   ocurrido_at: string,
     *   hora: string,
     *   tipo: string,
     *   titulo: string,
     *   detalle: string,
     *   actor: ?string,
     *   orden: int
     * }>
     */
    public function ejecutar(array $filtros): array
    {
        $desde = Carbon::parse($filtros['desde']);
        $hasta = Carbon::parse($filtros['hasta']);
        $userId = $filtros['user_id'];
        $sucursalId = $filtros['sucursal_id'];

        $filas = array_merge(
            $this->jornadas($userId, $sucursalId, $desde, $hasta),
            $this->pausas($userId, $sucursalId, $desde, $hasta),
            $this->turnos($userId, $sucursalId, $desde, $hasta),
            $this->esperas($userId, $sucursalId, $desde, $hasta),
        );

        usort($filas, function (array $a, array $b): int {
            $tiempo = strcmp($b['ocurrido_at'], $a['ocurrido_at']);
            if ($tiempo !== 0) {
                return $tiempo;
            }

            return $b['orden'] <=> $a['orden'];
        });

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jornadas(int $userId, int $sucursalId, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $jornadas = JornadaPdv::query()
            ->where('user_id', $userId)
            ->where('sucursal_id', $sucursalId)
            ->where('apertura_at', '<=', $hasta)
            ->where(function ($query) use ($desde): void {
                $query->whereNull('cierre_at')->orWhere('cierre_at', '>=', $desde);
            })
            ->orderBy('apertura_at')
            ->get();

        $filas = [];
        foreach ($jornadas as $jornada) {
            if ($jornada->apertura_at !== null && $jornada->apertura_at->between($desde, $hasta)) {
                $filas[] = $this->fila(
                    'jornada-'.$jornada->id.'-apertura',
                    $jornada->apertura_at,
                    'jornada.abierta',
                    'Jornada abierta',
                    'La jornada queda abierta en esta sucursal.',
                    null,
                    10,
                );
            }
            if ($jornada->cierre_at !== null && $jornada->cierre_at->between($desde, $hasta)) {
                $filas[] = $this->fila(
                    'jornada-'.$jornada->id.'-cierre',
                    $jornada->cierre_at,
                    'jornada.cerrada',
                    'Jornada cerrada',
                    'La jornada deja de estar abierta.',
                    null,
                    80,
                );
            }
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pausas(int $userId, int $sucursalId, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $intervalos = IntervaloOperativoPdv::query()
            ->with('motivoPausa')
            ->where('user_id', $userId)
            ->where('sucursal_id', $sucursalId)
            ->where('tipo', TipoIntervaloOperativoPdv::EnPausa)
            ->where('inicio_at', '<=', $hasta)
            ->where(function ($query) use ($desde): void {
                $query->whereNull('fin_at')->orWhere('fin_at', '>=', $desde);
            })
            ->orderBy('inicio_at')
            ->get();

        $actores = $this->nombres($intervalos->flatMap(fn (IntervaloOperativoPdv $intervalo): array => [
            $intervalo->pausa_iniciada_por_id,
            $intervalo->pausa_finalizada_por_id,
        ])->filter()->all());

        $filas = [];
        foreach ($intervalos as $intervalo) {
            $motivo = $intervalo->motivoPausa?->nombre ?? 'Sin motivo';
            if ($intervalo->inicio_at !== null && $intervalo->inicio_at->between($desde, $hasta)) {
                $filas[] = $this->fila(
                    'pausa-'.$intervalo->id.'-inicio',
                    $intervalo->inicio_at,
                    'pausa.iniciada',
                    'Pausa iniciada',
                    'Motivo: '.$motivo.'.',
                    $actores[$intervalo->pausa_iniciada_por_id] ?? null,
                    30,
                );
            }
            if ($intervalo->fin_at !== null && $intervalo->fin_at->between($desde, $hasta)) {
                $filas[] = $this->fila(
                    'pausa-'.$intervalo->id.'-fin',
                    $intervalo->fin_at,
                    'pausa.finalizada',
                    'Pausa finalizada',
                    'Duración: '.$this->duracion($intervalo->inicio_at, $intervalo->fin_at).'. Motivo: '.$motivo.'.',
                    $actores[$intervalo->pausa_finalizada_por_id] ?? null,
                    40,
                );
            }
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function turnos(int $userId, int $sucursalId, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $turnoIds = TurnoPdvAtencion::query()
            ->where('user_id', $userId)
            ->whereHas('turno', fn ($query) => $query->where('sucursal_id', $sucursalId))
            ->where(function ($query) use ($desde, $hasta): void {
                $query->whereBetween('inicio_at', [$desde, $hasta])
                    ->orWhereBetween('fin_at', [$desde, $hasta])
                    ->orWhere(function ($abierta) use ($hasta): void {
                        $abierta->whereNull('fin_at')->where('inicio_at', '<=', $hasta);
                    });
            })
            ->pluck('turno_id');

        if ($turnoIds->isEmpty()) {
            return [];
        }

        $eventos = TurnoPdvEvento::query()
            ->with(['turno', 'actor'])
            ->whereIn('turno_id', $turnoIds)
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->whereIn('tipo_evento', [
                TurnoPdvEvento::TIPO_ALTA,
                TurnoPdvEvento::TIPO_ASIGNADO,
                TurnoPdvEvento::TIPO_REATENCION,
                TurnoPdvEvento::TIPO_ATENCION_CERRADA,
                TurnoPdvEvento::TIPO_TRANSFERIDO,
            ])
            ->orderBy('ocurrido_at')
            ->get();

        $ids = [];
        foreach ($eventos as $evento) {
            $snapshot = is_array($evento->snapshot_json) ? $evento->snapshot_json : [];
            foreach (['user_id', 'origen_user_id', 'destino_user_id'] as $clave) {
                if (! empty($snapshot[$clave])) {
                    $ids[] = $snapshot[$clave];
                }
            }
        }
        $nombres = $this->nombres($ids);

        $filas = [];
        foreach ($eventos as $evento) {
            $folio = $evento->turno?->folio ?: ('#'.$evento->turno_id);
            $snapshot = is_array($evento->snapshot_json) ? $evento->snapshot_json : [];
            $tipo = (string) $evento->tipo_evento;

            if ($tipo === TurnoPdvEvento::TIPO_ALTA) {
                $filas[] = $this->fila(
                    'turno-'.$evento->id,
                    $evento->ocurrido_at,
                    'turno.alta',
                    'Turno '.$folio.' entra a la cola',
                    'El turno queda en espera de asignación.',
                    $evento->actor?->name,
                    20,
                );

                continue;
            }

            if ($tipo === TurnoPdvEvento::TIPO_ATENCION_CERRADA) {
                $filas[] = $this->fila(
                    'turno-'.$evento->id,
                    $evento->ocurrido_at,
                    'atencion.cerrada',
                    'Atención de '.$folio.' cerrada',
                    'Motivo: '.$this->motivoCierre($evento).'.',
                    $evento->actor?->name,
                    70,
                );

                continue;
            }

            if ($tipo === TurnoPdvEvento::TIPO_ASIGNADO || $tipo === TurnoPdvEvento::TIPO_REATENCION) {
                $recibeId = (int) ($snapshot['user_id'] ?? 0);
                if ($recibeId !== $userId) {
                    continue;
                }

                $recibe = $nombres[$recibeId] ?? 'la persona consultada';
                $filas[] = $this->fila(
                    'turno-'.$evento->id,
                    $evento->ocurrido_at,
                    $tipo,
                    $tipo === TurnoPdvEvento::TIPO_REATENCION
                        ? 'Turno '.$folio.' en reatención'
                        : 'Turno '.$folio.' asignado',
                    'Recibe: '.$recibe.'. Origen: '.$this->etiquetaOrigen((string) ($snapshot['origen'] ?? '')).'.',
                    $evento->actor?->name ?? 'Sistema',
                    60,
                );

                continue;
            }

            if ($tipo !== TurnoPdvEvento::TIPO_TRANSFERIDO) {
                continue;
            }

            $origenId = (int) ($snapshot['origen_user_id'] ?? 0);
            $destinoId = (int) ($snapshot['destino_user_id'] ?? 0);
            if ($origenId !== $userId && $destinoId !== $userId) {
                continue;
            }

            $filas[] = $this->fila(
                'turno-'.$evento->id,
                $evento->ocurrido_at,
                'turno.transferido',
                'Turno '.$folio.' transferido',
                'Entrega: '.($nombres[$origenId] ?? 'sin registro').'. Recibe: '.($nombres[$destinoId] ?? 'sin registro').'.',
                $evento->actor?->name,
                65,
            );
        }

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function esperas(int $userId, int $sucursalId, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $esperas = EsperaDisponibilidadPdv::query()
            ->with('actor')
            ->where('user_id', $userId)
            ->where('sucursal_id', $sucursalId)
            ->whereBetween('ocurrido_at', [$desde, $hasta])
            ->orderBy('ocurrido_at')
            ->get();

        $filas = [];
        foreach ($esperas as $espera) {
            $nuevo = $espera->disponible_desde_nuevo;
            $anterior = $espera->disponible_desde_anterior;
            $detalle = $espera->cooldown_segundos.' segundos. '
                .'Marca anterior: '.($anterior instanceof CarbonInterface ? $this->hora($anterior) : 'sin marca').'. '
                .'Marca nueva: '.($nuevo instanceof CarbonInterface ? $this->hora($nuevo) : 'sin marca, queda elegible al instante').'. '
                .'Origen: '.$this->etiquetaOrigen($espera->origen).'.';

            $filas[] = $this->fila(
                'espera-'.$espera->id,
                $espera->ocurrido_at,
                'espera.aplicada',
                'Espera aplicada',
                $detalle,
                $espera->actor?->name,
                50,
            );
        }

        return $filas;
    }

    /**
     * @param  list<int|string|null>  $ids
     * @return array<int, string>
     */
    private function nombres(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    private function motivoCierre(TurnoPdvEvento $evento): string
    {
        $motivo = (string) ($evento->snapshot_json['motivo'] ?? '');
        $detalle = trim((string) ($evento->snapshot_json['motivo_detalle'] ?? ''));
        $etiqueta = match ($motivo) {
            MotivosCierreAtencionTurnoPdv::VENTA => 'Venta',
            MotivosCierreAtencionTurnoPdv::SIN_VENTA => 'Sin venta',
            MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO => 'No se presentó',
            MotivosCierreAtencionTurnoPdv::TRANSFERENCIA => 'Transferencia',
            MotivosCierreAtencionTurnoPdv::OTRO => 'Otro',
            default => $motivo !== '' ? $motivo : 'Sin motivo',
        };

        return $detalle !== '' ? $etiqueta.': '.$detalle : $etiqueta;
    }

    private function etiquetaOrigen(string $origen): string
    {
        return match ($origen) {
            EsperaDisponibilidadPdv::ORIGEN_ATENCION_CERRADA => 'cierre de atención',
            EsperaDisponibilidadPdv::ORIGEN_PAUSA_FINALIZADA => 'fin de pausa',
            'cooldown' => 'espera entre turnos',
            'jornada.abierta' => 'apertura de jornada',
            'gerencia.reatencion' => 'reatención manual',
            '' => 'no registrado',
            default => $origen,
        };
    }

    private function duracion(?CarbonInterface $inicio, ?CarbonInterface $fin): string
    {
        if (! $inicio instanceof CarbonInterface || ! $fin instanceof CarbonInterface) {
            return 'no registrada';
        }

        $segundos = max(0, (int) $inicio->diffInSeconds($fin));
        if ($segundos < 60) {
            return $segundos.' s';
        }

        $minutos = intdiv($segundos, 60);
        $resto = $segundos % 60;

        return $resto > 0 ? $minutos.' min '.$resto.' s' : $minutos.' min';
    }

    /**
     * @return array{
     *   id: string,
     *   ocurrido_at: string,
     *   hora: string,
     *   tipo: string,
     *   titulo: string,
     *   detalle: string,
     *   actor: ?string,
     *   orden: int
     * }
     */
    private function fila(
        string $id,
        CarbonInterface $cuando,
        string $tipo,
        string $titulo,
        string $detalle,
        ?string $actor,
        int $orden,
    ): array {
        return [
            'id' => $id,
            'ocurrido_at' => $cuando->toIso8601String(),
            'hora' => $this->hora($cuando),
            'tipo' => $tipo,
            'titulo' => $titulo,
            'detalle' => $detalle,
            'actor' => $actor,
            'orden' => $orden,
        ];
    }

    private function hora(CarbonInterface $cuando): string
    {
        return Carbon::parse($cuando)->timezone((string) config('app.timezone'))->format('d/m/Y H:i:s');
    }
}
