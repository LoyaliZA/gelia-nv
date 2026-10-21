<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\AsignacionComisionEmpaque;
use App\Models\ComisionesEmpaque\AuditoriaComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteUnidadComisionEmpaque;
use App\Models\ComisionesEmpaque\PeriodoComisionEmpaque;
use App\Models\ComisionesEmpaque\RepartoComisionEmpaque;
use App\Models\ComisionesEmpaque\TarifaComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use App\Support\ComisionesEmpaque\RepartirCentavosComisionEmpaque;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmarCorteComisionEmpaqueService
{
    public function __construct(
        private RepartirCentavosComisionEmpaque $repartir,
        private ComprobarFuenteCorteComisionEmpaque $fuentes,
    ) {}

    /** @return array<string, mixed> */
    public function calcular(int $areaId, Carbon $desde, Carbon $hasta, bool $incluirTardias): array
    {
        $incluidas = $this->unidades($areaId, $desde, $hasta, $incluirTardias);
        $errores = [];
        $periodos = PeriodoComisionEmpaque::query()->where('area_id', $areaId)
            ->where('desde', '<=', $hasta)->where('hasta', '>', $desde)->get();
        $equipoPermanente = RepartoComisionEmpaque::permanente($areaId);
        foreach ($periodos as $periodo) {
            if (! $desde->eq($periodo->desde) || ! $hasta->copy()->addMicrosecond()->eq($periodo->hasta) || $incluirTardias) {
                $errores[] = 'Prepare el corte con las fechas completas de un solo periodo y sin incorporar empaques de periodos anteriores.';
            }
        }
        $tramos = [];
        $total = 0;
        $cajas = 0;
        $tipos = [];

        foreach ($incluidas as $unidad) {
            $cajas += (int) $unidad->cantidad_comisionable;
            $total += (int) $unidad->importe_centavos;
            foreach ($unidad->lineas as $linea) {
                if ($linea->es_bolsa) {
                    continue;
                }
                $nombre = $linea->tipo?->nombre ?: 'Caja sin tipo';
                $tipos[$nombre] = ($tipos[$nombre] ?? 0) + (int) $linea->cantidad;
            }

            $tarifa = TarifaComisionEmpaque::vigente($areaId, $unidad->empacado_at);
            $esperado = $tarifa ? (int) $unidad->cantidad_comisionable * (int) $tarifa->importe_centavos : null;
            if ($esperado === null || $esperado !== (int) $unidad->importe_centavos) {
                $errores[] = 'La tarifa vigente ya no coincide con el importe de '.($unidad->folio_pedido ?: $unidad->clave_origen).'. Actualice antes de confirmar.';

                continue;
            }

            $reparto = $equipoPermanente ?? RepartoComisionEmpaque::vigente($areaId, $unidad->empacado_at);
            if (! $reparto || $reparto->lineas->isEmpty()) {
                $errores[] = 'Configure el equipo y reparto del área. No hay reparto para el '.$unidad->empacado_at->timezone(config('app.timezone'))->format('Y-m-d H:i').' para '.$unidad->folio_pedido.'.';

                continue;
            }
            $clave = (string) $reparto->id;
            if (! isset($tramos[$clave])) {
                $tramos[$clave] = [
                    'reparto_id' => $reparto->id,
                    'desde' => $reparto->vigente_desde?->toIso8601String(),
                    'hasta' => $reparto->vigente_hasta?->toIso8601String(),
                    'importe_centavos' => 0,
                    'lineas' => $reparto->lineas->map(fn ($l) => [
                        'participante_id' => (int) $l->participante_id,
                        'nombre' => $l->participante?->nombre,
                        'puntos_base' => (int) $l->puntos_base,
                        'porcentaje' => DineroComisionEmpaque::porcentaje((int) $l->puntos_base),
                    ])->all(),
                    'montos' => [],
                ];
            }
            $tramos[$clave]['importe_centavos'] += (int) $unidad->importe_centavos;
        }

        $asignaciones = [];
        foreach ($tramos as $clave => $tramo) {
            $montos = $this->repartir->repartir($tramo['importe_centavos'], $tramo['lineas']);
            $tramos[$clave]['montos'] = $montos;
            foreach ($tramo['lineas'] as $linea) {
                $importe = $montos[$linea['participante_id']] ?? 0;
                $asignaciones[] = [
                    'participante_id' => $linea['participante_id'],
                    'reparto_id' => $tramo['reparto_id'],
                    'importe_centavos' => $importe,
                ];
                $tramos[$clave]['participantes'][] = [
                    'participante_id' => $linea['participante_id'],
                    'nombre' => $linea['nombre'],
                    'porcentaje' => $linea['porcentaje'],
                    'importe' => DineroComisionEmpaque::texto($importe),
                ];
            }
            $tramos[$clave]['importe'] = DineroComisionEmpaque::texto($tramo['importe_centavos']);
        }

        $canon = [
            'area' => $areaId,
            'desde' => $desde->toIso8601String(),
            'hasta' => $hasta->toIso8601String(),
            'tardias' => $incluirTardias,
            'unidades' => $incluidas->map(fn (UnidadComisionEmpaque $u) => [$u->id, (int) $u->importe_centavos, (int) $u->cantidad_comisionable])->values()->all(),
            'asignaciones' => $asignaciones,
            'repartos' => collect($tramos)->map(fn ($tramo) => $tramo['lineas'])->all(),
        ];

        $exclusiones = UnidadComisionEmpaque::query()
            ->with('area:id,nombre')
            ->where('area_id', $areaId)
            ->whereNull('corte_id')
            ->where('situacion', '!=', UnidadComisionEmpaque::ELEGIBLE)
            ->where('situacion', '!=', UnidadComisionEmpaque::RECHAZADA)
            ->whereBetween('empacado_at', [$desde, $hasta])
            ->limit(80)
            ->get()
            ->map(fn (UnidadComisionEmpaque $u) => [
                'id' => $u->id,
                'folio_pedido' => $u->folio_pedido,
                'situacion' => UnidadComisionEmpaque::ETIQUETAS[$u->situacion] ?? $u->situacion,
                'diferencia' => $u->diferencia,
            ]);

        return [
            'errores' => array_values(array_unique($errores)),
            'hash' => hash('sha256', json_encode($canon)),
            'cajas' => $cajas,
            'importe' => DineroComisionEmpaque::texto($total),
            'importe_centavos' => $total,
            'tipos' => collect($tipos)->map(fn ($cantidad, $nombre) => ['nombre' => $nombre, 'cantidad' => $cantidad])->values()->all(),
            'tramos' => array_values($tramos),
            'unidades' => $incluidas->map(fn (UnidadComisionEmpaque $u) => $this->fila($u))->values()->all(),
            'exclusiones' => $exclusiones,
        ];
    }

    public function guardarBorrador(int $areaId, Carbon $desde, Carbon $hasta, bool $incluirTardias, User $responsable): CorteComisionEmpaque
    {
        $calculo = $this->calcular($areaId, $desde, $hasta, $incluirTardias);
        $corte = CorteComisionEmpaque::query()->create([
            'area_id' => $areaId,
            'estado' => CorteComisionEmpaque::BORRADOR,
            'limite_desde' => $desde,
            'limite_hasta' => $hasta,
            'incluye_tardias' => $incluirTardias,
            'cajas' => $calculo['cajas'],
            'total_centavos' => $calculo['importe_centavos'],
            'hash_calculo' => $calculo['hash'],
            'simulacion' => $calculo,
            'creado_por_id' => $responsable->id,
        ]);
        AuditoriaComisionEmpaque::anotar($responsable, 'corte', $corte->id, 'borrador', null, []);

        return $corte;
    }

    public function confirmar(int $areaId, Carbon $desde, Carbon $hasta, bool $incluirTardias, string $hash, User $responsable, ?int $borradorId = null): CorteComisionEmpaque
    {
        return DB::transaction(function () use ($areaId, $desde, $hasta, $incluirTardias, $hash, $responsable, $borradorId) {
            AreaComisionEmpaque::query()->whereKey($areaId)->lockForUpdate()->firstOrFail();
            $this->assertSinSolape($areaId, $desde, $hasta);

            $ids = $this->unidades($areaId, $desde, $hasta, $incluirTardias)->pluck('id')->sort()->values();
            $bloqueadas = UnidadComisionEmpaque::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            foreach ($bloqueadas as $unidad) {
                $diferencia = $this->fuentes->diferencia($unidad);
                if ($diferencia) {
                    throw ValidationException::withMessages(['corte' => $diferencia]);
                }
            }
            if ($bloqueadas->contains(fn (UnidadComisionEmpaque $u) => $u->corte_id !== null || $u->situacion !== UnidadComisionEmpaque::ELEGIBLE)) {
                throw ValidationException::withMessages([
                    'corte' => 'Alguna unidad ya no está disponible. Actualice la vista previa y revise el cálculo de nuevo.',
                ]);
            }

            $calculo = $this->calcular($areaId, $desde, $hasta, $incluirTardias);
            if ($calculo['errores'] !== []) {
                throw ValidationException::withMessages(['corte' => $calculo['errores'][0]]);
            }
            if (! hash_equals($calculo['hash'], $hash)) {
                throw ValidationException::withMessages([
                    'corte' => 'Los datos cambiaron desde la vista previa. Revise el cálculo actualizado y confirme de nuevo.',
                ]);
            }
            if ($bloqueadas->isEmpty()) {
                throw ValidationException::withMessages(['corte' => 'No hay unidades elegibles en el intervalo.']);
            }

            $corte = $borradorId
                ? CorteComisionEmpaque::query()->where('estado', CorteComisionEmpaque::BORRADOR)->lockForUpdate()->find($borradorId)
                : null;
            if ($borradorId && ! $corte) {
                throw ValidationException::withMessages(['borrador' => 'El borrador ya no existe o ya fue confirmado.']);
            }

            $folio = $this->folio($hasta);
            $atributos = [
                'folio' => $folio,
                'area_id' => $areaId,
                'estado' => CorteComisionEmpaque::CONFIRMADO,
                'limite_desde' => $desde,
                'limite_hasta' => $hasta,
                'incluye_tardias' => $incluirTardias,
                'cajas' => $calculo['cajas'],
                'total_centavos' => $calculo['importe_centavos'],
                'hash_calculo' => $calculo['hash'],
                'simulacion' => null,
                'confirmado_at' => now(),
                'confirmado_por_id' => $responsable->id,
                'creado_por_id' => $corte?->creado_por_id ?: $responsable->id,
            ];

            if ($corte) {
                $corte->update($atributos);
            } else {
                $corte = CorteComisionEmpaque::query()->create($atributos);
            }

            foreach ($bloqueadas as $unidad) {
                $unidad->load('lineas.tipo', 'area');
                CorteUnidadComisionEmpaque::query()->create([
                    'corte_id' => $corte->id,
                    'unidad_id' => $unidad->id,
                    'clave_origen' => $unidad->clave_origen,
                    'incorporacion_tardia' => $unidad->incorporacion_tardia,
                    'excepcion_resguardo' => $unidad->excepcion_resguardo,
                    'empacado_at' => $unidad->empacado_at,
                    'cantidad_comisionable' => (int) $unidad->cantidad_comisionable,
                    'importe_centavos' => (int) $unidad->importe_centavos,
                    'desglose' => $unidad->lineas->map(fn ($l) => [
                        'tipo' => $l->es_bolsa ? 'Bolsa excluida' : ($l->tipo?->nombre ?: 'Caja sin tipo'),
                        'cantidad' => (int) $l->cantidad,
                        'es_bolsa' => (bool) $l->es_bolsa,
                    ])->values()->all(),
                    'referencia' => [
                        'folio_pedido' => $unidad->folio_pedido,
                        'folio_remision' => $unidad->folio_remision,
                        'cliente' => $unidad->cliente_nombre,
                        'empacador' => $unidad->empacador_nombre,
                        'logistica' => $unidad->logistica,
                        'procedencia' => $unidad->procedencia,
                    ],
                ]);
                $unidad->corte_id = $corte->id;
                $unidad->situacion = UnidadComisionEmpaque::EN_CORTE;
                $unidad->save();
            }

            foreach ($calculo['tramos'] as $tramo) {
                foreach ($tramo['lineas'] as $linea) {
                    $importe = $tramo['montos'][$linea['participante_id']] ?? 0;
                    AsignacionComisionEmpaque::query()->create([
                        'corte_id' => $corte->id,
                        'participante_id' => $linea['participante_id'],
                        'reparto_id' => $tramo['reparto_id'],
                        'tramo_desde' => $tramo['desde'],
                        'tramo_hasta' => $tramo['hasta'],
                        'puntos_base' => $linea['puntos_base'],
                        'importe_centavos' => $importe,
                        'pagado_centavos' => 0,
                    ]);
                }
            }

            AuditoriaComisionEmpaque::anotar($responsable, 'corte', $corte->id, 'confirmar', null, ['folio' => $folio]);

            return $corte->fresh(['asignaciones.participante', 'unidades', 'area']);
        });
    }

    private function unidades(int $areaId, Carbon $desde, Carbon $hasta, bool $incluirTardias)
    {
        return UnidadComisionEmpaque::query()
            ->with(['lineas.tipo', 'area:id,nombre'])
            ->where('area_id', $areaId)
            ->where('situacion', UnidadComisionEmpaque::ELEGIBLE)
            ->whereNull('corte_id')
            ->where(function ($q) use ($desde, $hasta, $incluirTardias) {
                $q->whereBetween('empacado_at', [$desde, $hasta]);
                if ($incluirTardias) {
                    $q->orWhere(function ($q2) use ($desde) {
                        $q2->where('incorporacion_tardia', true)->where('empacado_at', '<', $desde);
                    });
                }
            })
            ->orderBy('empacado_at')
            ->orderBy('id')
            ->get();
    }

    private function assertSinSolape(int $areaId, Carbon $desde, Carbon $hasta): void
    {
        $solapa = CorteComisionEmpaque::query()
            ->where('area_id', $areaId)
            ->where('estado', CorteComisionEmpaque::CONFIRMADO)
            ->where('limite_desde', '<=', $hasta)
            ->where('limite_hasta', '>=', $desde)
            ->exists();

        if ($solapa) {
            throw ValidationException::withMessages([
                'intervalo' => 'Ya hay un corte confirmado que cubre parte de este intervalo. Elija fechas que no se traslapen.',
            ]);
        }
    }

    private function folio(Carbon $hasta): string
    {
        $anio = $hasta->timezone(config('app.timezone'))->year;
        $ultimo = CorteComisionEmpaque::query()
            ->where('folio', 'like', 'CE-'.$anio.'-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('folio');
        $seq = 1;
        if (is_string($ultimo) && preg_match('/(\d+)$/', $ultimo, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return sprintf('CE-%d-%05d', $anio, $seq);
    }

    /** @return array<string, mixed> */
    private function fila(UnidadComisionEmpaque $u): array
    {
        return [
            'id' => $u->id,
            'empacado_at' => $u->empacado_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'cliente' => $u->cliente_nombre,
            'folio_pedido' => $u->folio_pedido,
            'folio_remision' => $u->folio_remision,
            'empacador' => $u->empacador_nombre,
            'cantidad' => $u->cantidad_comisionable,
            'importe' => $u->importe_centavos === null ? null : DineroComisionEmpaque::texto((int) $u->importe_centavos),
            'incorporacion_tardia' => $u->incorporacion_tardia,
            'excepcion_resguardo' => $u->excepcion_resguardo,
        ];
    }
}
