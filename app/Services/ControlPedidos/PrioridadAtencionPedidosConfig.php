<?php

namespace App\Services\ControlPedidos;

use App\Models\ConfiguracionSistema;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido as Modalidad;
use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio as Operacion;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Database\Eloquent\Builder;

final class PrioridadAtencionPedidosConfig
{
    public const CLAVE = 'control_pedidos.prioridad_atencion';

    public const TIPOS = [
        'mismo_dia' => 'Mismo día', 'municipal' => 'Municipales', 'tienda' => 'Recoge en tienda',
        'resguardo' => 'Resguardos', 'nacional' => 'Envíos nacionales',
    ];

    public function obtener(): array
    {
        $orden = json_decode(ConfiguracionSistema::where('clave', self::CLAVE)->value('valor') ?? 'null', true);
        $tipos = array_keys(self::TIPOS);

        return is_array($orden) && count($orden) === count($tipos) && count(array_unique($orden)) === count($tipos)
            && ! array_diff($orden, $tipos) ? array_values($orden) : $tipos;
    }

    public function guardar(array $orden): void
    {
        ConfiguracionSistema::updateOrCreate(['clave' => self::CLAVE], [
            'valor' => json_encode(array_values($orden)), 'tipo' => 'json', 'grupo' => 'ControlPedidos',
            'descripcion' => 'Jerarquía de atención de pedidos',
        ]);
    }

    /** Clasificación estable, posición configurable. Ordena antes de paginar, sin cambiar requisitos. */
    private function expresionTipo(array $rangos): array
    {
        $base = PedidoBma::query();
        $grammar = $base->getQuery()->getGrammar();
        $entrega = $grammar->wrap('pedidos_bma.solicitud_preparacion_json->modalidad_entrega');
        $modalidad = $grammar->wrap('pedidos_bma.solicitud_preparacion_json->codigo_modalidad');
        $modalidad = "COALESCE($modalidad, (select m.codigo from pedido_bma_tareas_preparacion t join catalogo_modalidades_preparacion_pedido m on m.id = t.catalogo_modalidad_preparacion_id where t.pedido_bma_id = pedidos_bma.id and t.estado not in ('CANCELADA', 'LIBERADA') order by t.version desc, t.id desc limit 1))";
        $municipalLegado = "($entrega IS NULL AND (select sin_logistica from catalogo_paqueterias_pedido where id = pedidos_bma.catalogo_paqueteria_id) = 1)";
        $operacion = '(select codigo from catalogo_tipos_operacion_envio where id = pedidos_bma.tipo_operacion_envio_id)';
        $sql = "CASE WHEN pedidos_bma.prioridad_md = ? THEN ?
            WHEN pedidos_bma.es_resguardo = ? OR $operacion IN (?, ?) THEN ?
            WHEN $entrega = ? OR $modalidad = ? OR $operacion = ? OR $municipalLegado THEN ?
            WHEN $entrega = ? OR $modalidad IN (?, ?, ?) THEN ? ELSE ? END";
        $bindings = [true, $rangos['mismo_dia'], true, Operacion::CODIGO_RESGUARDO_ABIERTO, Operacion::CODIGO_RESGUARDO_COMPLEMENTARIO, $rangos['resguardo'],
            'municipal', Modalidad::CODIGO_ENVIO_MUNICIPIO, Operacion::CODIGO_MUNICIPIO_DIFERIDO, $rangos['municipal'],
            'tienda', ...Modalidad::CODIGOS_FASE4, $rangos['tienda'], $rangos['nacional']];
        return [$sql, $bindings];
    }

    /** Reutiliza la clasificación de la jerarquía; los resguardos permanecen en Para tienda. */
    public function filtrarTipo(Builder $query, string $tipo): void
    {
        if (! in_array($tipo, ['tienda', 'mismo_dia', 'municipal', 'nacional'], true)) {
            return;
        }
        [$sql, $bindings] = $this->expresionTipo([
            'mismo_dia' => 1, 'municipal' => 2, 'tienda' => 3, 'resguardo' => 3, 'nacional' => 4,
        ]);
        $valor = ['mismo_dia' => 1, 'municipal' => 2, 'tienda' => 3, 'nacional' => 4][$tipo];
        $query->whereRaw("($sql) = ?", [...$bindings, $valor]);
    }

    public function ordenar(Builder $query, bool $tareas = false, string $orden = 'antiguos'): void
    {
        [$sql, $bindings] = $this->expresionTipo(array_flip($this->obtener()));
        $base = PedidoBma::query();
        $direccion = $orden === 'recientes' ? 'desc' : 'asc';
        $query->reorder();
        if ($tareas) {
            $base->selectRaw($sql, $bindings)->whereColumn('pedidos_bma.id', 'pedido_bma_tareas_preparacion.pedido_bma_id');
            $query->orderBy($base)->orderBy('solicitada_at', $direccion)->orderBy('pedido_bma_tareas_preparacion.id', $direccion);
        } else {
            $query->orderByRaw($sql, $bindings)->orderByRaw('COALESCE(pesaje_solicitado_at, pedidos_bma.created_at) '.$direccion)->orderBy('pedidos_bma.id', $direccion);
        }
    }
}
