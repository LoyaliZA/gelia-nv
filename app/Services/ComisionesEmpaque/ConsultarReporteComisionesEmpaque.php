<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\TarifaComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque as Dinero;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ConsultarReporteComisionesEmpaque
{
    public function __construct(
        private AlcanceAreaComisionEmpaque $alcance,
        private ConfirmarCorteComisionEmpaqueService $calculo,
    ) {}

    public function consultar(Request $request, User $usuario, string $zona, bool $detalle = false): array
    {
        $inicio = $request->string('desde')->toString();
        $fin = $request->string('hasta')->toString();
        $desde = Dinero::inicioDia($inicio, $zona);
        $hasta = Dinero::finDia($fin, $zona);
        $areas = $this->alcance->areas($usuario)->filter(fn ($a) => ! $request->filled('area_id') || $a->id === $request->integer('area_id'));
        $grupos = [];
        $filas = [];
        $firmas = [];

        foreach ($areas as $area) {
            $base = UnidadComisionEmpaque::query()->where('area_id', $area->id)->whereBetween('empacado_at', [$desde, $hasta]);
            $sinCorte = (clone $base)->whereNull('corte_id');
            $unidades = (clone $sinCorte)->disponibleParaCorte()->orderBy('empacado_at')->orderBy('id')->get();
            $calculo = $this->calculo->calcular($area->id, $desde, $hasta, false, $unidades);
            $pendientes = (clone $sinCorte)->whereIn('situacion', UnidadComisionEmpaque::POR_REVISAR)->count();
            $tardios = (clone $sinCorte)->where('incorporacion_tardia', true)->count();
            $excluidos = (clone $sinCorte)->whereNotIn('situacion', [...UnidadComisionEmpaque::POR_REVISAR, UnidadComisionEmpaque::ELEGIBLE])
                ->where('incorporacion_tardia', false)->count();
            $cortes = CorteComisionEmpaque::query()->where('area_id', $area->id)
                ->where('estado', CorteComisionEmpaque::CONFIRMADO)
                ->where('limite_desde', '<=', $hasta)->where('limite_hasta', '>=', $desde)
                ->with(['asignaciones' => fn ($q) => $q->with(['participante', 'reparto.lineas'])->orderBy('id')])->orderBy('limite_desde')->orderBy('id')->get();
            $parciales = [];
            foreach ($cortes as $corte) {
                if ($corte->limite_desde->lt($desde) || $corte->limite_hasta->gt($hasta)) {
                    $parciales[] = ['id' => $corte->id, 'folio' => $corte->folio];
                    continue;
                }
                $corte->load(['unidades' => fn ($q) => $detalle ? $q->orderBy('empacado_at')->orderBy('id')
                    : $q->select(['id', 'corte_id', 'cantidad_comisionable', 'importe_centavos'])]);
                $historico = $this->historico($corte, $area->nombre, $zona);
                $grupos[] = $historico;
                if ($detalle) {
                    foreach ($corte->unidades as $unidad) {
                        $referencia = $unidad->referencia ?? [];
                        $filas[] = $this->fila(
                            $area->nombre, $corte->folio, $unidad->empacado_at?->copy()->timezone($zona)->format('Y-m-d H:i'),
                            $referencia['cliente'] ?? null, $referencia['folio_remision'] ?? null,
                            $referencia['folio_pedido'] ?? null, $referencia['empacador'] ?? null,
                            (int) $unidad->cantidad_comisionable, (int) $unidad->importe_centavos,
                            $referencia['logistica'] ?? [], $referencia['conciliacion'] ?? [], 'Liquidación confirmada',
                        );
                    }
                }
            }

            $tramos = collect($calculo['tramos'])->map(fn ($tramo) => [
                'desde' => substr($tramo['desde'] ?? '', 0, 10),
                'importe' => $tramo['importe'],
                'participantes' => $tramo['participantes'] ?? [],
            ])->all();
            $referencias = $unidades->isEmpty() && ($cortes->isEmpty() || $pendientes || $tardios || $excluidos || $parciales)
                ? TarifaComisionEmpaque::query()->where('area_id', $area->id)
                    ->where('vigente_desde', '<=', $hasta)
                    ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', $desde))
                    ->orderBy('vigente_desde')->get()->map(fn ($tarifa) => [
                        'tarifa' => Dinero::texto($tarifa->importe_centavos),
                        'desde' => $tarifa->vigente_desde->copy()->timezone($zona)->format('Y-m-d H:i'),
                    ])->all()
                : [];
            $grupo = [
                'area_id' => $area->id, 'area' => $area->nombre, 'desde' => $inicio, 'hasta' => $fin,
                'corte' => null, 'folio' => null, 'cajas' => $calculo['cajas'], 'importe' => $calculo['importe'],
                'tarifas' => $this->tarifas($unidades), 'tarifas_referencia' => $referencias, 'tramos' => $tramos,
                'pendientes' => $pendientes, 'tardios' => $tardios, 'excluidos' => $excluidos,
                'cortes_parciales' => $parciales, 'reparto_incompleto' => $calculo['reparto_incompleto'] && ($unidades->isNotEmpty() || $cortes->isEmpty()),
                'advertencias' => $calculo['errores'],
            ];
            $firmas[] = $calculo['hash'];
            if ($unidades->isNotEmpty() || $cortes->isEmpty() || $pendientes || $tardios || $excluidos || $parciales) {
                $grupos[] = $grupo;
            }

            if ($detalle) {
                $validas = array_fill_keys($unidades->modelKeys(), true);
                $sinCorte->orderBy('empacado_at')->orderBy('id')->with('lineas')->chunk(250, function ($lote) use (&$filas, $validas, $area, $zona) {
                    foreach ($lote as $unidad) {
                        $valida = isset($validas[$unidad->id]);
                        $conciliacion = $unidad->conciliacionVigente() ?? [];
                        $filas[] = $this->fila(
                            $area->nombre, null, $unidad->empacado_at?->copy()->timezone($zona)->format('Y-m-d H:i'),
                            $unidad->cliente_nombre, $unidad->folio_remision, $unidad->folio_pedido, $unidad->empacador_nombre,
                            $valida ? (int) $unidad->cantidad_comisionable : 0,
                            $valida ? (int) $unidad->importe_centavos : null,
                            $unidad->logistica ?? [], $conciliacion, $unidad->incorporacion_tardia
                                ? 'Empaque de periodo cerrado' : (UnidadComisionEmpaque::ETIQUETAS[$unidad->situacion] ?? $unidad->situacion),
                            $unidad->notas,
                            $unidad->lineas->isEmpty() ? null : (int) $unidad->lineas->where('es_bolsa', false)->sum('cantidad'),
                            $unidad->incorporacion_tardia ? $unidad->importe_centavos : null,
                        );
                    }
                });
            }
        }

        return [
            'desde' => $inicio, 'hasta' => $fin, 'grupos' => $grupos,
            'version' => hash('sha256', json_encode([$inicio, $fin, $grupos, $firmas], JSON_THROW_ON_ERROR)),
            'detalle' => $filas,
        ];
    }

    private function historico(CorteComisionEmpaque $corte, string $area, string $zona): array
    {
        $tramos = $corte->asignaciones->groupBy('reparto_id')->map(fn ($grupo) => [
            'desde' => $grupo->first()->tramo_desde?->copy()->timezone($zona)->toDateString(),
            'importe' => Dinero::texto((int) $grupo->sum('importe_centavos')),
            'participantes' => $grupo->map(fn ($a) => [
                'participante_id' => $a->participante_id, 'nombre' => $a->participante?->nombre,
                'color' => $a->reparto?->lineas->firstWhere('participante_id', $a->participante_id)?->color,
                'porcentaje' => Dinero::porcentaje((int) $a->puntos_base),
                'importe' => Dinero::texto((int) $a->importe_centavos),
            ])->values()->all(),
        ])->values()->all();

        return [
            'area_id' => $corte->area_id, 'area' => $area,
            'desde' => $corte->limite_desde->copy()->timezone($zona)->toDateString(),
            'hasta' => $corte->limite_hasta->copy()->timezone($zona)->toDateString(),
            'corte' => $corte->id, 'folio' => $corte->folio,
            'cajas' => $corte->cajas, 'importe' => Dinero::texto($corte->total_centavos),
            'tarifas' => $this->tarifas($corte->unidades), 'tramos' => $tramos,
        ];
    }

    private function tarifas(Collection $unidades): array
    {
        return $unidades->filter(fn ($u) => $u->cantidad_comisionable > 0 && $u->importe_centavos !== null)
            ->groupBy(fn ($u) => intdiv((int) $u->importe_centavos, (int) $u->cantidad_comisionable))
            ->sortKeys()->map(fn ($grupo, $centavos) => [
                'tarifa' => Dinero::texto((int) $centavos),
                'cajas' => (int) $grupo->sum('cantidad_comisionable'),
                'importe' => Dinero::texto((int) $grupo->sum('importe_centavos')),
            ])->values()->all();
    }

    private function fila(string $area, ?string $folio, ?string $fecha, ?string $cliente, ?string $remision, ?string $pedido,
        ?string $empacador, int $cajas, ?int $importe, array $logistica, array $conciliacion, string $estado,
        ?string $notas = null, ?int $cajasRegistradas = null, ?int $potencial = null): array
    {
        $remisiones = collect($logistica['remisiones'] ?? [])->pluck('folio')->filter()->unique()->implode(', ');
        $motivo = $conciliacion['motivo_comercial'] ?? $logistica['motivo_fuente'] ?? 'sin_informacion';

        return [
            'Área' => $area, 'Corte' => $folio, 'Fecha de empaque' => $fecha, 'Cliente' => $cliente,
            'Remisiones' => $remisiones ?: $remision, 'Folio de pedido' => $pedido, 'Empacador' => $empacador,
            'Cajas comisionables' => $cajas, 'Cajas registradas' => $cajasRegistradas ?? ($folio ? $cajas : null),
            'Tarifa' => $cajas && $importe !== null ? intdiv($importe, $cajas) / 100 : null,
            'Comisión' => $importe === null ? null : $importe / 100,
            'Importe potencial' => $potencial === null ? null : $potencial / 100,
            'Motivo comercial' => UnidadComisionEmpaque::MOTIVOS_COMERCIALES[$motivo] ?? $motivo,
            'Observaciones' => $conciliacion['observaciones'] ?? $notas ?? $logistica['observaciones'] ?? null,
            'Estado' => $estado,
        ];
    }
}
