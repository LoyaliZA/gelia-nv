<?php

namespace App\Services\ControlPedidos\Nacional\Multienvio;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\SaldosAFavor\SafPedidoAplicacion;
use App\Support\ControlPedidos\Nacional\VisibilidadMiembrosMultienvio;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Collection;

/**
 * Lectura del Multienvío para un usuario (sin N+1). Diseño A20: el grupo nunca concede permiso;
 * `envios`, `totales` y `progreso` cubren solo los miembros que el usuario ya puede consultar.
 */
class ProyeccionMultienvio
{
    public function __construct(private readonly EvaluarMultienvioNacional $evaluador) {}

    /** @return array<string, mixed> */
    public function de(PedidoBma $pedido, User $usuario): array
    {
        $grupo = $this->grupo($pedido);
        $origen = $grupo !== null && $grupo->pedido_origen_id !== $pedido->id ? $grupo->origen : $pedido;
        $origenVisible = $origen !== null && VisibilidadPedidoBma::puedeConsultar($usuario, $origen);
        $evaluacion = $this->evaluador->ejecutar($origenVisible ? $origen : $pedido);

        $todos = $grupo === null ? collect() : $this->miembros($grupo);
        $visibles = (new VisibilidadMiembrosMultienvio($usuario))->filtrar($todos);
        $parcial = $visibles->count() < $todos->count();

        $envios = $visibles->map(fn (PedidoBma $m) => $this->envio($m, $grupo))->all();

        return [
            'evaluacion' => $evaluacion,
            'grupo' => $grupo === null ? null : $this->grupoArray($grupo, $origen, $origenVisible, $usuario, $todos->isNotEmpty() && ! $parcial),
            'envios' => $envios,
            'cajas_origen' => $this->cajasOrigen($pedido, $grupo, $origen, $usuario),
            'cajas_resumen' => $this->cajasResumen($pedido, $grupo, $origen, $evaluacion, $usuario),
            'parcial' => $parcial,
            'totales' => $this->totales($envios),
            'progreso' => $this->progreso($envios),
        ];
    }

    private function grupo(PedidoBma $pedido): ?MultienvioNacional
    {
        return $pedido->multienvio_nacional_id
            ? MultienvioNacional::query()->with('origen')->find($pedido->multienvio_nacional_id)
            : MultienvioNacional::query()->with('origen')->where('pedido_origen_id', $pedido->id)->first();
    }

    /** @return Collection<int, PedidoBma> */
    private function miembros(MultienvioNacional $grupo): Collection
    {
        return PedidoBma::query()
            ->where('multienvio_nacional_id', $grupo->id)
            ->with(['estatus', 'vendedor.departamentos', 'cajas' => fn ($q) => $q->activas()->orderBy('orden')])
            ->withSum(['pagosExhibicion as pagado' => fn ($q) => $q->where('estado_revision', '!=', PedidoBmaPago::REVISION_RECHAZADO)], 'monto')
            ->withSum(['safAplicaciones as saf_aplicado' => fn ($q) => $q->where('estado', SafPedidoAplicacion::ESTADO_APLICADO)], 'monto')
            ->withCount([
                'pagosExhibicion as pagos_capturados',
                'guiaPdf as guias_pdf' => fn ($q) => $q->where('activo', true),
                'remision as remisiones_vigentes',
            ])
            ->orderBy('multienvio_orden')
            ->get();
    }

    /** @return array<string, mixed> */
    private function grupoArray(MultienvioNacional $grupo, ?PedidoBma $origen, bool $origenVisible, User $usuario, bool $veTodos): array
    {
        $propietaria = $origen !== null && VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $origen);

        return [
            'id' => $grupo->id,
            'uuid' => $origenVisible ? $grupo->uuid : null,
            'estado' => $grupo->estado,
            'decision' => match ($grupo->estado) {
                MultienvioNacional::ESTADO_ESTANDAR_SIN_SEGURO => 'sin_seguro',
                MultienvioNacional::ESTADO_PENDIENTE_DECISION => null,
                default => 'activar',
            },
            // El borrador (folios, montos y cajas de todos los envíos) solo es de la vendedora propietaria y solo mientras se configura;
            // la regularización, de ella o de quien ve todos los envíos. Nadie más: A20.
            'borrador' => $propietaria && $grupo->estado === MultienvioNacional::ESTADO_CONFIGURANDO ? $grupo->borrador_json : null,
            'regularizacion' => $propietaria || $veTodos ? $grupo->regularizacion_json : null,
            'pedido_origen_id' => $origenVisible ? $grupo->pedido_origen_id : null,
        ];
    }

    /**
     * Cajas activas del origen para configurar el multienvío. Solo la vendedora dueña, solo mientras `configurando`
     * y solo al leer el propio origen; las cajas ya movidas a otro pedido no pertenecen al origen y no salen.
     *
     * @return list<array<string, mixed>>|null
     */
    private function cajasOrigen(PedidoBma $pedido, ?MultienvioNacional $grupo, ?PedidoBma $origen, User $usuario): ?array
    {
        if ($grupo === null || $grupo->estado !== MultienvioNacional::ESTADO_CONFIGURANDO
            || $origen === null || $origen->id !== $pedido->id
            || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $origen)) {
            return null;
        }

        $cajas = $origen->cajas()->activas()->with('tipoCaja')->orderBy('orden')->get();
        $ids = $cajas->pluck('id')->all();
        $evidencias = collect();
        if ($ids !== [] && VisibilidadPedidoBma::puedeConsultarDocumentos($usuario, $origen, PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)) {
            $evidencias = PedidoBmaDocumento::query()
                ->where('pedido_bma_id', $origen->id)
                ->where('tipo', PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION)
                ->where('activo', true)
                ->where(fn ($q) => $q->whereIn('pedido_bma_caja_id', $ids)
                    ->orWhere(fn ($r) => $r->where('relacion_tipo', PedidoBmaDocumento::RELACION_ENVIO_CAJA)->whereIn('relacion_id', $ids)))
                ->orderBy('id')
                ->get();
        }

        return $cajas->map(fn (PedidoBmaCaja $c) => [
            'uuid_operativo' => $c->uuid_operativo,
            'orden' => (int) $c->orden,
            'tipo' => $c->tipoCaja?->nombre,
            'peso_cobrado_kg' => $c->peso_cobrado_kg,
            'largo' => $c->largo,
            'ancho' => $c->ancho,
            'alto' => $c->alto,
            'evidencias' => $evidencias
                ->filter(fn (PedidoBmaDocumento $d) => (int) $d->pedido_bma_caja_id === $c->id
                    || ($d->relacion_tipo === PedidoBmaDocumento::RELACION_ENVIO_CAJA && (int) $d->relacion_id === $c->id))
                ->map(fn (PedidoBmaDocumento $d) => ['id' => $d->id, 'url' => $d->url])
                ->values()->all(),
        ])->values()->all();
    }

    /**
     * Insumo del aviso «Multienvío detectado»: solo la dueña, con elegibilidad y decisión aún pendiente.
     *
     * @param  array<string, mixed>  $evaluacion
     * @return array{n: int, tipos: list<string>, peso_total_kg: float}|null
     */
    private function cajasResumen(PedidoBma $pedido, ?MultienvioNacional $grupo, ?PedidoBma $origen, array $evaluacion, User $usuario): ?array
    {
        $pendiente = $grupo === null || $grupo->estado === MultienvioNacional::ESTADO_PENDIENTE_DECISION;
        if (! $evaluacion['elegible'] || ! $pendiente || ($origen !== null && $origen->id !== $pedido->id)
            || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            return null;
        }
        $cajas = $pedido->cajas()->activas()->with('tipoCaja')->get();

        return [
            'n' => $cajas->count(),
            'tipos' => $cajas->map(fn (PedidoBmaCaja $c) => $c->tipoCaja?->nombre)->filter()->unique()->values()->all(),
            'peso_total_kg' => round((float) $cajas->sum('peso_cobrado_kg'), 3),
        ];
    }

    /** @return array<string, mixed> */
    private function envio(PedidoBma $m, MultienvioNacional $grupo): array
    {
        $fase = $m->estatus?->fase_ciclo;
        $caja = $m->cajas->first();
        $pagado = round((float) ($m->pagado ?? 0), 2);

        return [
            'id' => $m->id,
            'folio' => $m->folio,
            'orden' => (int) $m->multienvio_orden,
            'es_origen' => $m->id === $grupo->pedido_origen_id,
            'folio_remision' => $m->folio_remision,
            'total_mercancia' => (float) $m->total_mercancia,
            'costo_envio' => (float) $m->costo_envio,
            'costo_seguro' => (float) $m->costo_seguro,
            'total_a_cobrar' => (float) $m->total_a_cobrar,
            'caja_uuid' => $caja?->uuid_operativo,
            'estado_pago' => match (true) {
                $m->pago_validado_at !== null => 'validado',
                (int) $m->pagos_capturados > 0 => 'capturado',
                default => 'sin_pago',
            },
            'pagado' => $pagado,
            'pendiente' => round(max((float) $m->total_a_cobrar - $pagado, 0), 2),
            // Solo lectura (ruta de datos para saldos a favor): suma de aplicaciones ya aplicadas; reservas y liberadas no cuentan.
            'saf_aplicado' => round((float) ($m->saf_aplicado ?? 0), 2),
            'numero_remision' => $m->numero_remision,
            'tiene_remision' => trim((string) $m->numero_remision) !== '' || (int) $m->remisiones_vigentes > 0,
            'tiene_guia' => trim((string) $m->numero_rastreo) !== '' || (int) $m->guias_pdf > 0
                || $m->cajas->contains(fn ($c) => trim((string) $c->numero_rastreo) !== ''),
            'empacado' => $m->empacado_at !== null,
            'enviado' => in_array($fase, [CatalogoEstatusPedido::FASE_ENVIADO, CatalogoEstatusPedido::FASE_ENTREGADO], true),
            'estatus' => $m->estatus?->nombre_visual,
            'fase' => $fase,
        ];
    }

    /** @param  list<array<string, mixed>>  $envios */
    private function totales(array $envios): array
    {
        $suma = fn (string $campo) => round(array_sum(array_column($envios, $campo)), 2);
        $total = $suma('total_a_cobrar');
        $pagado = $suma('pagado');

        return [
            'mercancia' => $suma('total_mercancia'),
            'flete' => $suma('costo_envio'),
            'seguro' => $suma('costo_seguro'),
            'total' => $total,
            'pagado' => $pagado,
            'pendiente' => round(max($total - $pagado, 0), 2),
        ];
    }

    /** @param  list<array<string, mixed>>  $envios */
    private function progreso(array $envios): array
    {
        $n = count($envios);
        $cuenta = fn (callable $f) => count(array_filter($envios, $f)).'/'.$n;

        return [
            'pagos_validados' => $cuenta(fn ($e) => $e['estado_pago'] === 'validado'),
            'remisiones' => $cuenta(fn ($e) => $e['tiene_remision']),
            'empacados' => $cuenta(fn ($e) => $e['empacado']),
            'guias' => $cuenta(fn ($e) => $e['tiene_guia']),
            'enviados' => $cuenta(fn ($e) => $e['enviado']),
        ];
    }
}
