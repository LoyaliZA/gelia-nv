<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\Schema;

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
}
