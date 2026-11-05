<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Notifications\AlertaPedidoBma;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ApartadoT01;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\PlazoApartadoTienda;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Confirmación de Ventas al auxiliar; no registra cobro ni separación física. */
final class ConfirmarPedidoTiendaService
{
    public function ejecutar(PedidoBma $pedido, User $actor): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $actor) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = $pedido->solicitud_preparacion_json ?? [];
            $cobertura = $json['cobertura_tienda'] ?? [];
            if (! VisibilidadPedidoBma::puedeMutarComoVendedora($actor, $pedido)) {
                throw ValidationException::withMessages(['confirmacion' => 'Solo la vendedora del pedido puede confirmar.']);
            }
            if (! ApartadoT01::aplica($pedido) || ($cobertura['tipo'] ?? '') !== 'apartado') {
                throw ValidationException::withMessages(['confirmacion' => 'Guarde Pago al recoger antes de confirmar.']);
            }
            if ($pedido->cancelado_at || ! $pedido->consultaCerrada() || $pedido->motivoBloqueoCierreConsultaTienda()) {
                throw ValidationException::withMessages(['confirmacion' => 'Cierre la consulta vigente y resuelva los ajustes antes de confirmar.']);
            }
            // Un reintento no notifica dos veces ni renueva el plazo.
            if (isset($cobertura['confirmacion_enviada_at'])) {
                return $pedido;
            }
            $tarea = InstruccionEntrega::abiertas((int) $pedido->id)->first();
            if ($tarea) {
                $tarea = InstruccionEntrega::bloquearParaEjecutar($tarea);
            }
            $auxiliar = $tarea?->asignada_a_id ? User::find($tarea->asignada_a_id) : null;
            if (! $auxiliar) {
                throw ValidationException::withMessages(['confirmacion' => 'Falta el auxiliar responsable de la consulta.']);
            }
            $plazo = app(PlazoApartadoTienda::class)->evaluarConfirmacion(now(), isset($cobertura['vence_at']) ? Carbon::parse($cobertura['vence_at']) : null);
            if (! $plazo['permite']) {
                throw ValidationException::withMessages(['confirmacion' => $plazo['mensaje']]);
            }
            $json['cobertura_tienda'] = array_merge($cobertura, [
                'vence_at' => $plazo['vence_at']->toIso8601String(),
                'requiere_confirmacion_pedido' => true,
                'confirmacion_enviada_at' => now()->toIso8601String(),
                'confirmacion_enviada_por_id' => $actor->id,
            ]);
            unset($json['cobertura_tienda']['motivo_vigencia']);
            $pedido->update(['solicitud_preparacion_json' => $json]);
            $tarea->update(['fecha_limite' => $plazo['vence_at']]);
            $tarea->cumplimientoFisico()->update(['vence_at' => $plazo['vence_at']]);
            app(RegistrarHistorialPedidoService::class)->ejecutar($pedido->id, $actor->id,
                $pedido->catalogo_estatus_pedido_id, $pedido->catalogo_estatus_pedido_id,
                'Confirmación de Pago al recoger enviada al auxiliar de tienda.', AccionesHistorialPedidoBma::CORRECCION);
            DB::afterCommit(fn () => $auxiliar->notify(new AlertaPedidoBma($pedido,
                'pedido_confirmado_tienda', 'Pedido confirmado por Ventas. Separe y conserve el apartado; el cliente paga al recoger.',
                ['url' => '/control-pedidos/tienda/tareas/'.$tarea->id])));

            return $pedido->fresh();
        });
    }
}
