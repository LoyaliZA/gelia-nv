<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Devuelve el pedido cuando el crédito está vencido, antes de la remisión. */
final class DevolverCreditoVencidoTiendaService
{
    public const INDICACION = 'El cliente tiene un crédito vencido y necesita liquidarlo.';

    public function ejecutar(PedidoBma $pedido, User $usuario, string $causa = ''): PedidoBma
    {
        if (! $usuario->can('control_pedidos.auditar')) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien audita registra el resultado de crédito.',
            ]);
        }

        $causa = trim($causa);
        $indicacion = $causa !== '' && $causa !== self::INDICACION
            ? self::INDICACION.' '.$causa
            : self::INDICACION;

        return DB::transaction(function () use ($pedido, $usuario, $indicacion) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if (! $pedido->esAuditablePorAuxiliar()) {
                throw new \RuntimeException('Solo se puede devolver un pedido pendiente de revisión.');
            }

            $codigoCobertura = $pedido->codigoModalidadPreparacionVigente()
                ?: (is_array($pedido->solicitud_preparacion_json) ? ($pedido->solicitud_preparacion_json['codigo_modalidad'] ?? null) : null);
            if ($codigoCobertura !== CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO) {
                throw ValidationException::withMessages([
                    'modalidad' => 'Este pedido no está en cobertura de crédito.',
                ]);
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $json['credito_wizerp'] = [
                'resultado' => 'vencido',
                'causa' => $indicacion,
                'registrado_at' => now()->toIso8601String(),
                'registrado_por_id' => $usuario->id,
                'corte_cancelacion' => 'inactivo',
                'pendiente' => null,
            ];
            $pedido->update(['solicitud_preparacion_json' => $json]);

            return app(RechazarPedidoBmaService::class)->ejecutar($pedido->fresh(), $usuario->id, $indicacion);
        });
    }
}
