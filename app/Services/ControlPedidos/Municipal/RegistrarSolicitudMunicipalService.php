<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\Conversion\ValidarCapturaMunicipal;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarSolicitudMunicipalService
{
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages(['pedido' => 'No puede modificar esta solicitud.']);
        }

        return DB::transaction(function () use ($pedido, $usuario, $datos) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if (! $pedido->esEditablePorVendedora() || $pedido->cancelado_at || $pedido->pedidoPagadoORemisionado()) {
                throw ValidationException::withMessages(['pedido' => 'Este pedido ya no admite cambios en la solicitud.']);
            }
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            if (($json['modalidad_entrega'] ?? null) !== 'municipal') {
                throw ValidationException::withMessages(['modalidad' => 'Este pedido no es un envío municipal.']);
            }
            if (ConsultaMunicipal::estado($pedido) === ConsultaMunicipal::CERRADA && ($json['modalidad_entrega'] ?? null) === 'municipal') {
                throw ValidationException::withMessages(['consulta' => 'La consulta ya está cerrada.']);
            }

            if (! empty($json['consulta_municipal']['enviada_at'])) {
                throw ValidationException::withMessages(['consulta' => 'La solicitud ya fue enviada. Resuelva la consulta antes de cambiar sus datos.']);
            }

            $captura = app(ValidarCapturaMunicipal::class)->ejecutar($pedido, $datos);
            $sucursalDestinoId = $this->sucursalDestino($pedido, $datos);
            if (array_key_exists('almacen_id', $datos)) {
                // Intención de procedencia; enviar la consulta sigue siendo un hecho separado.
                $json['almacen_revision_id'] = $captura['almacen_id'];
            }

            $json['modalidad_entrega'] = 'municipal';
            $json['codigo_modalidad'] = CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO;
            $json['expediente'] = $captura['expediente'];
            if ($sucursalDestinoId !== null) {
                $json['recorrido_fisico_municipal'] = true;
            }
            $json['finanzas_municipales'] = [
                'costo_transporte' => $captura['costo'],
                'leyenda_terminal' => $captura['leyenda'],
                'aviso_costo_posterior_at' => $captura['costo'] === 'diferido' ? now()->toIso8601String() : null,
            ];
            $tipo = $captura['costo'] === 'diferido'
                ? CatalogoTipoOperacionEnvio::porCodigo(CatalogoTipoOperacionEnvio::CODIGO_MUNICIPIO_DIFERIDO)
                : null;
            $pedido->update([
                'solicitud_preparacion_json' => $json,
                'sucursal_destino_id' => $sucursalDestinoId ?? $pedido->sucursal_destino_id,
                'catalogo_paqueteria_id' => $captura['paqueteria']->id,
                'costo_envio' => $captura['monto'],
                'total_a_cobrar' => PedidoBma::calcularTotal((float) $pedido->total_mercancia, $captura['monto'] ?? 0, (bool) $pedido->aplica_seguro, (float) $pedido->costo_seguro, (float) $pedido->saldo_a_favor),
                'tipo_operacion_envio_id' => $tipo?->id ?? CatalogoTipoOperacionEnvio::idNormal(),
                'estatus_envio' => $captura['costo'] === 'diferido' ? PedidoBma::ESTATUS_ENVIO_PENDIENTE_REGULARIZACION : $pedido->estatus_envio,
            ]);

            return $pedido->fresh();
        });
    }

    /**
     * Sucursal PDV que recibe el envío. Solo el guardado de la solicitud la resuelve; la conversión
     * desde otra modalidad conserva la sucursal del pedido sin validarla contra la lista piloto.
     *
     * @param  array<string, mixed>  $datos
     */
    private function sucursalDestino(PedidoBma $pedido, array $datos): ?int
    {
        $solicitada = ($datos['sucursal_destino_id'] ?? null) ? (int) $datos['sucursal_destino_id'] : ($pedido->sucursal_destino_id ? (int) $pedido->sucursal_destino_id : null);
        $resolver = app(ResolverSucursalDestinoMunicipal::class);

        // Sin sucursales habilitadas y sin solicitud (catálogo sin configurar) el destino queda sin definir.
        return $solicitada === null && $resolver->habilitadas() === []
            ? null
            : $resolver->ejecutar($solicitada);
    }
}
