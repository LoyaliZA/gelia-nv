<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;
use App\Support\ControlPedidos\RegistrarPedidos\DestinoRevisionRegistrarPedidos;
use App\Support\ControlPedidos\RegistrarPedidos\LimiteModalidadCapturaPedido;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Guarda modalidad, referencia adicional y entrega municipal sin abrir una tarea. */
class PersistirIntencionPreparacionPedido
{
    public function __construct(
        private FusionarSolicitudPreparacionPedido $fusionar,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(PedidoBma $pedido, array $datos): PedidoBma
    {
        $cambios = [];

        if (array_key_exists('prioridad_md', $datos) && Schema::hasColumn('pedidos_bma', 'prioridad_md')) {
            $cambios['prioridad_md'] = filter_var($datos['prioridad_md'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('solicitud_preparacion', $datos)) {
            if (! Schema::hasColumn('pedidos_bma', 'solicitud_preparacion_json')) {
                throw new \RuntimeException(
                    'La intención de preparación no se guardó: el esquema aún no tiene la columna. El guardado no quedó confirmado.'
                );
            }

            $entrada = $datos['solicitud_preparacion'];
            if (is_array($entrada)) {
                $actual = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
                $this->rechazarCambioDeDestinoEnviado($pedido, $actual, $entrada);
                $motivo = LimiteModalidadCapturaPedido::bloqueado(
                    $this->pedidoAvanzado($pedido),
                    $actual,
                    $entrada
                );
                if ($motivo !== null) {
                    throw ValidationException::withMessages(['modalidad_entrega' => $motivo]);
                }
            }
            $cambios['solicitud_preparacion_json'] = $this->fusionar->ejecutar(
                is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : null,
                is_array($entrada) ? $entrada : null,
            );
        }

        if ($cambios !== []) {
            $pedido->update($cambios);
        }

        return $pedido;
    }

    private function pedidoAvanzado(PedidoBma $pedido): bool
    {
        if ($pedido->tieneRemision() || $pedido->tienePagoValidado() || filled($pedido->numero_remision)) {
            return true;
        }

        if (InstruccionEntrega::abiertas((int) $pedido->id)->isNotEmpty()) {
            return true;
        }

        if (DestinoRevisionRegistrarPedidos::yaEnviada($pedido)) {
            return true;
        }

        return $pedido->pagosExhibicion()->where('activo_para_cobertura', true)->exists();
    }

    /**
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $entrada
     */
    private function rechazarCambioDeDestinoEnviado(PedidoBma $pedido, array $actual, array $entrada): void
    {
        if (! DestinoRevisionRegistrarPedidos::yaEnviada($pedido)) {
            return;
        }

        foreach (['area_revision', 'almacen_revision_id', 'caso_tienda'] as $clave) {
            if (! array_key_exists($clave, $entrada)) {
                continue;
            }
            $nuevo = $entrada[$clave];
            $previo = $actual[$clave] ?? null;
            if ((string) ($nuevo ?? '') !== (string) ($previo ?? '')) {
                throw ValidationException::withMessages([
                    'area_revision' => DestinoRevisionRegistrarPedidos::MENSAJE_DESTINO_FIJO,
                ]);
            }
        }
    }
}
