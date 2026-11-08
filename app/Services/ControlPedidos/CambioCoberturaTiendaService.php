<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\CambioCoberturaTienda;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CambioCoberturaTiendaService
{
    public function ejecutar(PedidoBma $pedido, User $actor, array $datos): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $actor, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            abort_unless(VisibilidadPedidoBma::puedeConsultar($actor, $pedido), 403);
            $json = $pedido->solicitud_preparacion_json ?? [];
            $actual = $json['cambio_cobertura_tienda'] ?? [];
            $accion = $datos['accion'];
            if ($accion === 'solicitar') {
                abort_unless($actor->can('control_pedidos.crear') && VisibilidadPedidoBma::puedeMutarComoVendedora($actor, $pedido), 403);
                // Reintento exacto: no duplica historial ni notificaciones.
                if (($actual['id'] ?? '') === $datos['solicitud_id']) return $pedido;
                foreach ($json['historial_cambios_cobertura'] ?? [] as $previa) {
                    if (($previa['id'] ?? '') === $datos['solicitud_id']) $this->error('Esta solicitud ya fue resuelta. Actualice el pedido.');
                }
                if (($actual['estado'] ?? '') === 'pendiente') $this->error('Ya hay una solicitud pendiente. Espere su resolución.');
                if ($pedido->cancelado_at || $pedido->estatus?->fase_ciclo === 'ENTREGADO') $this->error('El pedido ya está cerrado.');
                if (! hash_equals(CambioCoberturaTienda::version($pedido), $datos['version'])) $this->error('El pedido cambió. Actualice antes de solicitar otra modalidad.');
                $anterior = $json['cobertura_tienda'] ?? [];
                if (empty($anterior['tipo'])) $this->error('Guarde primero la modalidad inicial.');
                $mismoDia = (bool) ($datos['mismo_dia'] ?? false);
                if ($mismoDia && $datos['cobertura'] !== 'transferencia') $this->error('Mismo día corresponde a transferencia.');
                if ($datos['cobertura'] === $anterior['tipo'] && $mismoDia === (($json['atencion_tienda'] ?? '') === 'prevista')) $this->error('Seleccione una modalidad o atención distinta.');
                $actual = [
                    'id' => $datos['solicitud_id'], 'estado' => 'pendiente',
                    'anterior' => $anterior, 'atencion_anterior' => $json['atencion_tienda'] ?? 'no',
                    'cobertura' => $datos['cobertura'], 'mismo_dia' => $mismoDia,
                    'motivo' => trim($datos['motivo']), 'solicitada_por_id' => $actor->id,
                    'solicitada_por' => $actor->name, 'solicitada_at' => now()->toIso8601String(),
                    'version_pedido' => CambioCoberturaTienda::version($pedido),
                ];
                $texto = 'Cambio de modalidad de pago solicitado: '.$anterior['tipo'].' → '.$actual['cobertura'].'. Motivo: '.$actual['motivo'].'. Pendiente de autorización; conserva la modalidad vigente.';
            } else {
                abort_unless($actor->can(CambioCoberturaTienda::PERMISO), 403);
                if (($actual['id'] ?? '') !== $datos['solicitud_id']) $this->error('La solicitud cambió. Actualice antes de resolver.');
                if ((int) ($actual['solicitada_por_id'] ?? 0) === (int) $actor->id) $this->error('La autorización debe realizarla otra persona.');
                if (($actual['estado'] ?? '') === ($accion === 'autorizar' ? 'aplicada' : 'rechazada')) return $pedido;
                if (($actual['estado'] ?? '') !== 'pendiente') $this->error('La solicitud ya fue resuelta.');
                if ($accion === 'autorizar') {
                    return app(RegistrarCoberturaTiendaService::class)->ejecutar($pedido, $actor, $actual['cobertura'], (bool) $actual['mismo_dia'], $actual['id'], trim($datos['motivo']));
                }
                $actual['estado'] = 'rechazada';
                $actual['resuelta_por_id'] = $actor->id;
                $actual['resuelta_por'] = $actor->name;
                $actual['resuelta_at'] = now()->toIso8601String();
                $actual['motivo_resolucion'] = trim($datos['motivo']);
                $json['historial_cambios_cobertura'][] = $actual;
                $texto = 'Cambio de modalidad de pago rechazado. Motivo: '.$actual['motivo_resolucion'];
            }
            $json['cambio_cobertura_tienda'] = $actual;
            $pedido->update(['solicitud_preparacion_json' => $json]);
            app(RegistrarHistorialPedidoService::class)->ejecutar($pedido->id, $actor->id, $pedido->catalogo_estatus_pedido_id, $pedido->catalogo_estatus_pedido_id, $texto, AccionesHistorialPedidoBma::CORRECCION);
            app(NotificarPedidoBmaService::class)->ejecutar($pedido, 'pedido_cambio_cobertura', $texto, [CambioCoberturaTienda::PERMISO], $actor->id, true);

            return $pedido->fresh(['estatus']);
        });
    }

    private function error(string $mensaje): never
    {
        throw ValidationException::withMessages(['modalidad' => $mensaje]);
    }
}
