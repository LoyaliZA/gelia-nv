<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\AlmacenPdv;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnviarConsultaMunicipalService
{
    public function ejecutar(PedidoBma $pedido, User $usuario, ?int $almacenId): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages(['pedido' => 'No puede enviar esta consulta.']);
        }

        return DB::transaction(function () use ($pedido, $usuario, $almacenId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if (! $pedido->esEditablePorVendedora() || $pedido->cancelado_at || $pedido->pedidoPagadoORemisionado()) {
                throw ValidationException::withMessages(['pedido' => 'Este pedido ya no admite cambios en la solicitud.']);
            }
            $pedido->loadMissing('estatus');
            if (! ConsultaMunicipal::es($pedido)) {
                throw ValidationException::withMessages(['modalidad' => 'Guarde primero la solicitud municipal.']);
            }
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
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $previo = is_array($json['consulta_municipal'] ?? null) ? $json['consulta_municipal'] : [];
            if (empty($json['expediente']['version_datos_id']) || ! $pedido->catalogo_paqueteria_id || empty($json['finanzas_municipales'])) {
                throw ValidationException::withMessages(['solicitud' => 'Guarde primero los datos de la solicitud municipal.']);
            }
            if (! empty($previo['enviada_at'])) {
                if (($previo['almacen_id'] ?? null) != $almacen?->id) {
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
            if (! \App\Models\ClienteConsignatario::query()->whereKey($json['expediente']['consignatario_id'] ?? 0)
                ->where('cliente_id', $pedido->cliente_id)->where('activo', true)->exists()) {
                throw ValidationException::withMessages(['consignatario_id' => 'Seleccione un consignatario vigente del cliente del pedido.']);
            }
            if (! $pedido->paqueteria?->habilitadaParaRegistroMunicipal()) {
                throw ValidationException::withMessages(['catalogo_paqueteria_id' => 'Seleccione una terminal habilitada para envío municipal.']);
            }
            $json['consulta_municipal'] = [
                'area' => $area,
                'almacen_id' => $almacen?->id,
                'estado' => ConsultaMunicipal::PENDIENTE,
                'enviada_at' => $previo['enviada_at'] ?? now()->toIso8601String(),
                'enviada_por_id' => $previo['enviada_por_id'] ?? $usuario->id,
            ];
            unset($json['revision_disponibilidad']);
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
            app(\App\Services\ControlPedidos\NotificarPedidoBmaService::class)->ejecutar(
                $pedido->fresh(), 'pedido_consulta_municipal',
                $area === 'tienda' ? 'Consulta pendiente: Tienda' : 'Consulta pendiente: CEDIS',
                [$area === 'tienda' ? 'control_pedidos.municipal.responder_consulta' : 'control_pedidos.cedis'],
                $usuario->id, false,
                ['url' => $area === 'tienda' ? '/control-pedidos/municipal/consultas-tienda' : '/control-pedidos/cedis?q='.$pedido->folio]
            );

            return $pedido->fresh(['estatus', 'paqueteria']);
        });
    }
}
