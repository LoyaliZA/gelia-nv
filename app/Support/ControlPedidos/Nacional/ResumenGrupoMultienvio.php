<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\MultienvioNacional;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resumen de grupo de Multienvío para listas, calculado por lote (consultas constantes por página).
 *
 * Adjunta a cada pedido de la página que pertenece a un grupo (miembro) o es el origen de uno vigente el atributo
 * `multienvio`: id, orden, n_total, estado, parcial y hermanos (columnas ligeras). El grupo nunca concede permiso:
 * los hermanos salen solo si el usuario ya puede consultarlos (misma regla que ProyeccionMultienvio, vía
 * VisibilidadMiembrosMultienvio). Decisión de privacidad: `n_total` cuenta únicamente los miembros visibles para el
 * usuario; si hay ocultos solo se revela `parcial = true`, nunca cuántos son ni nada de ellos.
 */
final class ResumenGrupoMultienvio
{
    private const ESTADOS_ORIGEN = [
        MultienvioNacional::ESTADO_CONFIGURANDO,
        MultienvioNacional::ESTADO_ACTIVO,
        MultienvioNacional::ESTADO_REGULARIZACION_PENDIENTE,
    ];

    /** @param  Collection<int, PedidoBma>  $pedidos */
    public static function paraPagina(Collection $pedidos, User $usuario): void
    {
        $ids = $pedidos->pluck('id')->filter()->all();
        $idsGrupo = $pedidos->pluck('multienvio_nacional_id')->filter()->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        $grupos = MultienvioNacional::query()
            ->where(fn ($q) => $q->whereIn('id', $idsGrupo)
                ->orWhere(fn ($r) => $r->whereIn('pedido_origen_id', $ids)->whereIn('estado', self::ESTADOS_ORIGEN)))
            ->get(['id', 'pedido_origen_id', 'estado']);
        if ($grupos->isEmpty()) {
            return;
        }

        $miembros = PedidoBma::query()
            ->whereIn('multienvio_nacional_id', $grupos->pluck('id')->all())
            ->select(['id', 'folio', 'folio_remision', 'multienvio_nacional_id', 'multienvio_orden', 'numero_remision', 'numero_rastreo',
                'pago_validado_at', 'total_a_cobrar', 'catalogo_estatus_pedido_id', 'vendedor_id', 'estatus_envio', 'solicitud_preparacion_json'])
            ->with(['estatus', 'vendedor.departamentos', 'cajas' => fn ($q) => $q->activas()->select(['id', 'pedido_bma_id', 'numero_rastreo'])])
            ->withCount([
                'guiaPdf as guias_pdf' => fn ($q) => $q->where('activo', true),
                'remision as remisiones_vigentes',
            ])
            ->orderBy('multienvio_orden')
            ->get();

        // Los miembros de cualquier modalidad distinta de nacional no se resumen (columnas ligeras: no alcanzan para
        // evaluar su visibilidad); por construcción todos son nacionales.
        $visibilidad = new VisibilidadMiembrosMultienvio($usuario);
        $visibles = $miembros
            ->filter(fn (PedidoBma $m) => ($m->solicitud_preparacion_json['modalidad_entrega'] ?? null) === 'nacional' && $visibilidad->puede($m))
            ->groupBy('multienvio_nacional_id');
        $totales = $miembros->groupBy('multienvio_nacional_id')->map->count();
        $compartidos = self::referenciasCompartidas($miembros, $visibilidad);
        $porId = $grupos->keyBy('id');
        $porOrigen = $grupos->whereIn('estado', self::ESTADOS_ORIGEN)->keyBy('pedido_origen_id');

        foreach ($pedidos as $pedido) {
            $grupo = $pedido->multienvio_nacional_id ? $porId->get($pedido->multienvio_nacional_id) : $porOrigen->get($pedido->id);
            if ($grupo === null) {
                continue;
            }
            $lista = $visibles->get($grupo->id, collect());
            $pedido->setAttribute('multienvio', [
                'id' => $grupo->id,
                'orden' => $pedido->multienvio_orden !== null ? (int) $pedido->multienvio_orden : null,
                'n_total' => $lista->count(),
                'estado' => $grupo->estado,
                'parcial' => $lista->count() < (int) $totales->get($grupo->id, 0),
                'total_operacion' => round((float) $lista->sum('total_a_cobrar'), 2),
                'comprobantes_compartidos' => $compartidos[$grupo->id][$pedido->id] ?? [],
                'hermanos' => $lista->map(fn (PedidoBma $m) => [
                    'id' => $m->id,
                    'orden' => (int) $m->multienvio_orden,
                    'folio' => $m->folio,
                    'folio_remision' => $m->folio_remision,
                    'estatus_nombre' => $m->estatus?->nombre_visual,
                    'estatus_fase' => $m->estatus?->fase_ciclo,
                    'pago_validado' => $m->pago_validado_at !== null,
                    'tiene_remision' => trim((string) $m->numero_remision) !== '' || (int) $m->remisiones_vigentes > 0,
                    'tiene_guia' => trim((string) $m->numero_rastreo) !== '' || (int) $m->guias_pdf > 0
                        || $m->cajas->contains(fn ($c) => trim((string) $c->numero_rastreo) !== ''),
                ])->values()->all(),
            ]);
        }
    }

    /**
     * Referencias de pago que se repiten en exhibiciones de varios envíos visibles del mismo grupo (transferencia
     * compartida). Solo informativo: no concilia ni bloquea; la suma es la de las partes registradas en esos envíos.
     *
     * @return array<int, array<int, list<array{referencia: string, envios: list<int>, suma: float}>>>  grupo => pedido => avisos
     */
    private static function referenciasCompartidas(Collection $miembros, VisibilidadMiembrosMultienvio $visibilidad): array
    {
        $visibles = $miembros->filter(fn (PedidoBma $m) => ($m->solicitud_preparacion_json['modalidad_entrega'] ?? null) === 'nacional' && $visibilidad->puede($m))->keyBy('id');
        if ($visibles->count() < 2) {
            return [];
        }
        $pagos = PedidoBmaPago::query()
            ->whereIn('pedido_bma_id', $visibles->keys()->all())
            ->where('activo_para_cobertura', true)
            ->whereNotNull('referencia')->where('referencia', '!=', '')
            ->get(['id', 'pedido_bma_id', 'referencia', 'monto']);

        $salida = [];
        foreach ($pagos->groupBy(fn ($p) => $visibles[$p->pedido_bma_id]->multienvio_nacional_id.'|'.mb_strtolower(trim($p->referencia))) as $clave => $filas) {
            $pedidosConRef = $filas->pluck('pedido_bma_id')->unique();
            if ($pedidosConRef->count() < 2) {
                continue;
            }
            $grupoId = (int) explode('|', $clave)[0];
            $aviso = [
                'referencia' => trim($filas->first()->referencia),
                'envios' => $pedidosConRef->map(fn ($id) => (int) $visibles[$id]->multienvio_orden)->sort()->values()->all(),
                'suma' => round((float) $filas->sum('monto'), 2),
            ];
            foreach ($pedidosConRef as $id) {
                $salida[$grupoId][$id][] = $aviso;
            }
        }

        return $salida;
    }

    /** Resumen del propio pedido (para `LecturaConsultaNacional`); null si no pertenece a un grupo. */
    public static function de(PedidoBma $pedido, User $usuario): ?array
    {
        $copia = (clone $pedido)->unsetRelations();
        self::paraPagina(collect([$copia]), $usuario);

        // `multienvio` es también el nombre de una relación del modelo: se lee del arreglo de atributos para no cargarla.
        return $copia->getAttributes()['multienvio'] ?? null;
    }
}
