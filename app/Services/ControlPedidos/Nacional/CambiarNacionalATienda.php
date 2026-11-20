<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ControlPedidos\Direcciones\InvalidarGuiaPorCambioDireccion;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Custodia en sucursal antes de la salida. No enciende la conversión de modalidad. */
class CambiarNacionalATienda
{
    public function __construct(
        private InvalidarGuiaPorCambioDireccion $invalidarGuia,
        private EventosCustodiaSucursalNacional $eventos,
    ) {}

    /** @param  array<string, mixed>  $contexto */
    public function ejecutar(PedidoBma $pedido, User $usuario, int $sucursalId, array $contexto): PedidoBma
    {
        if (! CapturaNacional::es($pedido)) {
            throw new RuntimeException('Este pedido no usa la captura nacional.');
        }
        if (! Sucursal::query()->whereKey($sucursalId)->exists()) {
            throw new RuntimeException('La sucursal de custodia no existe.');
        }

        return DB::transaction(function () use ($pedido, $usuario, $sucursalId, $contexto) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $solicitudId = (string) ($contexto['solicitud_id'] ?? '');
            if ($solicitudId !== '' && (string) ($json['cambio_a_tienda']['solicitud_id'] ?? '') === $solicitudId) {
                return $pedido->fresh();
            }

            RevisionCapturaPedido::comprobar($pedido, $contexto);
            $pedido->loadMissing('estatus');
            if ($this->yaSalio($pedido)) {
                throw new RuntimeException('No se cambia a Tienda después de la salida.');
            }

            $saldoEnvio = $pedido->costo_envio;
            $remision = $pedido->numero_remision;
            $total = $pedido->total_mercancia;
            $pago = $pedido->pago_validado_at;
            if ($this->tieneGuia($pedido)) {
                $this->invalidarGuia->ejecutar($pedido, $usuario->id, 'Cambio a custodia de sucursal');
                $pedido = $pedido->fresh();
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $json['modalidad_entrega'] = 'nacional';
            $json['captura_nacional'] = 'compartida';
            $json['cambio_a_tienda'] = [
                'solicitud_id' => $solicitudId,
                'estado' => 'pendiente_recepcion',
                'sucursal_destino_id' => $sucursalId,
                'usuario_id' => $usuario->id,
                'at' => now()->toIso8601String(),
                'saldo_envio' => $saldoEnvio,
            ];
            unset($json['conversion_modalidad']);
            $pedido->update([
                'sucursal_destino_id' => $sucursalId,
                'numero_remision' => $remision,
                'total_mercancia' => $total,
                'pago_validado_at' => $pago,
                'costo_envio' => $saldoEnvio,
                'solicitud_preparacion_json' => $json,
            ]);
            $pedido->increment('revision_registro');
            $this->eventos->abrir($pedido->fresh(), $sucursalId, $usuario->id);

            return $pedido->fresh();
        });
    }

    private function yaSalio(PedidoBma $pedido): bool
    {
        $fase = $pedido->estatus?->fase_ciclo;
        if (in_array($fase, [
            CatalogoEstatusPedido::FASE_ENVIADO,
            CatalogoEstatusPedido::FASE_ENTREGADO,
            CatalogoEstatusPedido::FASE_EN_RUTA,
        ], true)) {
            return true;
        }

        return $pedido->cajas()->where('estatus_recoleccion', PedidoBmaCaja::ESTATUS_RECOLECTADA)->exists();
    }

    private function tieneGuia(PedidoBma $pedido): bool
    {
        return filled($pedido->numero_rastreo)
            || $pedido->documentos()->where('tipo', 'guia')->where('activo', true)->exists();
    }
}
