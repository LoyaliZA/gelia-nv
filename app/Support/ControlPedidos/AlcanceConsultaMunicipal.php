<?php

namespace App\Support\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\Municipal\EtiquetaConsultaMunicipal;
use Illuminate\Database\Eloquent\Builder;

final class AlcanceConsultaMunicipal
{
    public static function aplicarTienda(Builder $query, User $usuario): void
    {
        if (! VisibilidadPedidoBma::esAdmin($usuario)) {
            $query->whereIn('solicitud_preparacion_json->consulta_municipal->almacen_id',
                Almacen::query()->select('id')->whereIn('sucursal_id', $usuario->idsSucursalesOperables()));
        }
    }

    /**
     * Consultas municipales pendientes cuyo almacén es PDV (área tienda). El almacén indica dónde se prepara.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pendientesTienda(User $usuario, int $limite = 50): array
    {
        if (! $usuario->can('control_pedidos.municipal.responder_consulta')) {
            return [];
        }
        $query = PedidoBma::query()
            ->where('solicitud_preparacion_json->modalidad_entrega', 'municipal')
            ->where('solicitud_preparacion_json->consulta_municipal->area', 'tienda')
            ->whereIn('solicitud_preparacion_json->consulta_municipal->estado', [ConsultaMunicipal::PENDIENTE, ConsultaMunicipal::EXCEPCIONES]);
        self::aplicarTienda($query, $usuario);
        $pedidos = $query->with(['cliente:id,nombre,numero_cliente', 'paqueteria:id,nombre'])
            ->orderByDesc('id')->limit($limite)->get();
        $almacenes = Almacen::query()->whereIn('id', $pedidos->map(fn (PedidoBma $p) => ConsultaMunicipal::seguimiento($p)['almacen_id'] ?? null)->filter()->unique())
            ->pluck('nombre', 'id');

        return $pedidos->map(function (PedidoBma $pedido) use ($almacenes) {
            $almacenId = ConsultaMunicipal::seguimiento($pedido)['almacen_id'] ?? null;

            $visible = \App\Models\ControlPedidos\PedidoBmaReferencia::visible($pedido);

            return [
                'id' => $pedido->id,
                'folio' => $pedido->folio,
                'folio_visible' => $visible['folio'],
                'folio_etiqueta' => $visible['etiqueta'],
                'folio_remision' => $pedido->folio_remision,
                'enviada_at' => ConsultaMunicipal::seguimiento($pedido)['enviada_at'] ?? null,
                'cliente' => $pedido->cliente?->nombre,
                'terminal_destino' => $pedido->paqueteria?->nombre,
                'etiqueta' => EtiquetaConsultaMunicipal::de($pedido),
                'almacen_id' => $almacenId,
                'almacen' => $almacenId ? ($almacenes[$almacenId] ?? null) : null,
            ];
        })->values()->all();
    }

    /**
     * Consultas de PDV ya respondidas (o cerradas por Ventas) que aún no se aprueban. Sin esta lista el
     * pedido salía de Pendientes al responder y no aparecía en ninguna pestaña hasta el pago validado.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function atendidasTienda(User $usuario, int $limite = 50): array
    {
        if (! $usuario->can('control_pedidos.municipal.responder_consulta')) {
            return [];
        }
        $query = PedidoBma::query()
            ->where('solicitud_preparacion_json->modalidad_entrega', 'municipal')
            ->where('solicitud_preparacion_json->consulta_municipal->area', 'tienda')
            ->whereIn('solicitud_preparacion_json->consulta_municipal->estado', [ConsultaMunicipal::RESPONDIDA, ConsultaMunicipal::CERRADA])
            ->whereNull('pago_validado_at')
            ->whereNull('cancelado_at');
        self::aplicarTienda($query, $usuario);
        $pedidos = $query->with(['cliente:id,nombre,numero_cliente', 'paqueteria:id,nombre'])
            ->orderByDesc('id')->limit($limite)->get();
        $almacenes = Almacen::query()->whereIn('id', $pedidos->map(fn (PedidoBma $p) => ConsultaMunicipal::seguimiento($p)['almacen_id'] ?? null)->filter()->unique())
            ->pluck('nombre', 'id');

        return $pedidos->map(function (PedidoBma $pedido) use ($almacenes) {
            $consulta = ConsultaMunicipal::seguimiento($pedido);
            $almacenId = $consulta['almacen_id'] ?? null;
            $visible = \App\Models\ControlPedidos\PedidoBmaReferencia::visible($pedido);

            return [
                'id' => $pedido->id,
                'folio' => $pedido->folio,
                'folio_visible' => $visible['folio'],
                'folio_etiqueta' => $visible['etiqueta'],
                'folio_remision' => $pedido->folio_remision,
                'enviada_at' => $consulta['enviada_at'] ?? null,
                'cerrada' => ($consulta['estado'] ?? null) === ConsultaMunicipal::CERRADA,
                'cliente' => $pedido->cliente?->nombre,
                'terminal_destino' => $pedido->paqueteria?->nombre,
                'almacen_id' => $almacenId,
                'almacen' => $almacenId ? ($almacenes[$almacenId] ?? null) : null,
            ];
        })->values()->all();
    }

    /**
     * Municipales aprobados cuyo almacén es PDV: se empacan en Tienda.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function porEmpacarTienda(User $usuario, int $limite = 50): array
    {
        return self::filasEmpaqueTienda($usuario, $limite, empacados: false);
    }

    /**
     * Municipales de PDV ya empacados: visibles en Atendidos, sin reabrir CEDIS ni custodia.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function empacadosTienda(User $usuario, int $limite = 50): array
    {
        return self::filasEmpaqueTienda($usuario, $limite, empacados: true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function filasEmpaqueTienda(User $usuario, int $limite, bool $empacados): array
    {
        if (! $usuario->can('control_pedidos.municipal.responder_consulta')) {
            return [];
        }
        $fase = $empacados
            ? \App\Models\ControlPedidos\CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO
            : \App\Models\ControlPedidos\CatalogoEstatusPedido::FASE_EN_CEDIS;
        $query = PedidoBma::query()
            ->where('solicitud_preparacion_json->modalidad_entrega', 'municipal')
            ->where('solicitud_preparacion_json->consulta_municipal->area', 'tienda')
            ->where('solicitud_preparacion_json->consulta_municipal->estado', ConsultaMunicipal::CERRADA)
            ->whereNotNull('pago_validado_at')
            ->whereHas('remision')
            ->whereHas('estatus', fn ($e) => $e->where('fase_ciclo', $fase));
        if ($empacados) {
            $query->whereNotNull('empacado_at')->orderByDesc('empacado_at');
        } else {
            $query->whereNull('empacado_at')->orderBy('id');
        }
        self::aplicarTienda($query, $usuario);

        return $query->with(['cliente:id,nombre,numero_cliente', 'paqueteria:id,nombre'])->limit($limite)->get()
            ->map(function (PedidoBma $pedido) {
                $almacenId = ConsultaMunicipal::seguimiento($pedido)['almacen_id'] ?? null;
                $visible = \App\Models\ControlPedidos\PedidoBmaReferencia::visible($pedido);

                return [
                    'id' => $pedido->id,
                    'folio' => $pedido->folio,
                    'folio_visible' => $visible['folio'],
                    'folio_etiqueta' => $visible['etiqueta'],
                    'folio_remision' => $pedido->folio_remision,
                    'aprobado_at' => $pedido->pago_validado_at?->toIso8601String(),
                    'empacado_at' => $pedido->empacado_at?->toIso8601String(),
                    'cliente' => $pedido->cliente?->nombre,
                    'terminal_destino' => $pedido->paqueteria?->nombre,
                    'almacen_id' => $almacenId,
                    'almacen' => $almacenId ? Almacen::query()->whereKey($almacenId)->value('nombre') : null,
                ];
            })->values()->all();
    }

    /** Excluye de CEDIS los municipales que se preparan y empacan en un PDV. */
    public static function excluirEmpaqueTienda(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereNull('solicitud_preparacion_json->modalidad_entrega')
            ->orWhere('solicitud_preparacion_json->modalidad_entrega', '!=', 'municipal')
            ->orWhereNull('solicitud_preparacion_json->consulta_municipal->area')
            ->orWhere('solicitud_preparacion_json->consulta_municipal->area', '!=', 'tienda'));
    }

    public static function puedeResponder(User $usuario, PedidoBma $pedido): bool
    {
        if (! ConsultaMunicipal::es($pedido) || empty(ConsultaMunicipal::seguimiento($pedido)['enviada_at'])) {
            return false;
        }
        $consulta = ConsultaMunicipal::seguimiento($pedido);
        if (($consulta['area'] ?? null) === 'cedis') {
            return $usuario->can('control_pedidos.cedis') && VisibilidadPedidoBma::puedeVerEnCedis($usuario, $pedido);
        }
        if (($consulta['area'] ?? null) !== 'tienda' || ! $usuario->can('control_pedidos.municipal.responder_consulta')) {
            return false;
        }
        $almacen = Almacen::find($consulta['almacen_id'] ?? null);

        return $almacen && AlmacenPdv::esAlmacen($almacen)
            && (VisibilidadPedidoBma::esAdmin($usuario) || $usuario->idsSucursalesOperables()->contains((int) $almacen->sucursal_id));
    }
}
