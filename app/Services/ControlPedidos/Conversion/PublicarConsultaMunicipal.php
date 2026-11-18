<?php

namespace App\Services\ControlPedidos\Conversion;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\NotificarPedidoBmaService;
use App\Support\ControlPedidos\AlmacenPdv;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publica consulta municipal en contexto conversion. No pasa por los guards de alta pagada.
 */
class PublicarConsultaMunicipal
{
    public function ejecutar(PedidoBma $pedido, User $usuario, ?int $almacenId): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages(['pedido' => 'No puede enviar esta consulta.']);
        }

        return DB::transaction(function () use ($pedido, $usuario, $almacenId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $conversion = is_array($json['conversion_modalidad'] ?? null) ? $json['conversion_modalidad'] : [];
            if (($conversion['estado'] ?? null) !== CodigosConversionModalidad::ESTADO_CONFIRMADA
                || ($json['modalidad_entrega'] ?? null) !== CodigosConversionModalidad::DESTINO_MUNICIPAL) {
                throw ValidationException::withMessages(['conversion' => 'La consulta de conversión requiere un cambio a envío municipal confirmado.']);
            }
            $pedido->loadMissing('estatus');
            if (ConsultaMunicipal::estado($pedido) === ConsultaMunicipal::CERRADA) {
                throw ValidationException::withMessages(['consulta' => 'La consulta ya está cerrada.']);
            }
            $almacen = $almacenId ? Almacen::query()->with('tipoAlmacen')->find($almacenId) : null;
            if ($almacenId && ! $almacen) {
                throw ValidationException::withMessages(['almacen_id' => 'El almacén indicado no existe.']);
            }
            if ($almacen && (! $almacen->activo || ! $almacen->visible_en_pedidos)) {
                throw ValidationException::withMessages(['almacen_id' => 'Seleccione un almacén activo habilitado para pedidos.']);
            }
            $area = $almacen && AlmacenPdv::esAlmacen($almacen) ? 'tienda' : 'cedis';
            $previo = is_array($json['consulta_municipal'] ?? null) ? $json['consulta_municipal'] : [];
            $heredada = in_array($previo['procedencia'] ?? '', [
                CodigosConversionModalidad::PROCEDENCIA_HEREDADA_PARCIAL,
                CodigosConversionModalidad::PROCEDENCIA_HEREDADA_COMPLETA,
            ], true);
            if (empty($json['expediente']['version_datos_id']) || ! $pedido->catalogo_paqueteria_id || empty($json['finanzas_municipales'])) {
                throw ValidationException::withMessages(['solicitud' => 'Guarde primero los datos de la solicitud municipal.']);
            }
            if (! empty($previo['enviada_at'])) {
                if (($previo['almacen_id'] ?? null) != $almacen?->id && $almacenId) {
                    throw ValidationException::withMessages(['consulta' => 'La consulta ya fue enviada a otro almacén.']);
                }

                return $pedido->fresh(['estatus', 'paqueteria']);
            }
            if (! $pedido->cliente_id || ! $pedido->departamento_operacion_id || blank($pedido->folio_remision)) {
                throw ValidationException::withMessages(['pedido' => 'Complete cliente, departamento y folio de Wizerp antes de enviar la consulta.']);
            }
            if (! $pedido->documentos()->whereIn('tipo', ['pdf_pedido', 'cotizacion'])->where('activo', true)->exists()) {
                throw ValidationException::withMessages(['pdf_pedido' => 'Adjunte el PDF o la captura de Wizerp antes de enviar la consulta.']);
            }
            $json['consulta_municipal'] = array_merge($previo, [
                'area' => $area,
                'almacen_id' => $almacen?->id ?? ($previo['almacen_id'] ?? null),
                'estado' => ConsultaMunicipal::PENDIENTE,
                'enviada_at' => now()->toIso8601String(),
                'enviada_por_id' => $usuario->id,
                'contexto' => CodigosConversionModalidad::CONTEXTO_PUBLICACION,
            ]);
            if (! ($heredada && filled($previo['enviada_at'] ?? null))) {
                // Primera publicación: no hay revisión que conservar.
            }
            $cambios = ['solicitud_preparacion_json' => $json];
            $fase = $pedido->estatus?->fase_ciclo;
            if ($fase === CatalogoEstatusPedido::FASE_BORRADOR) {
                $destino = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
                if ($destino) {
                    MaquinaEstadosPedidoBma::assertTransicion($fase, CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
                    $cambios['catalogo_estatus_pedido_id'] = $destino->id;
                }
            }
            $pedido->update($cambios);
            app(NotificarPedidoBmaService::class)->ejecutar(
                $pedido->fresh(),
                'pedido_consulta_municipal',
                $area === 'tienda' ? 'Consulta pendiente: Tienda' : 'Consulta pendiente: CEDIS',
                [$area === 'tienda' ? 'control_pedidos.municipal.responder_consulta' : 'control_pedidos.cedis'],
                $usuario->id,
                false,
                ['url' => $area === 'tienda' ? '/control-pedidos/municipal/consultas-tienda' : '/control-pedidos/cedis?q='.$pedido->folio]
            );

            return $pedido->fresh(['estatus', 'paqueteria']);
        });
    }
}
