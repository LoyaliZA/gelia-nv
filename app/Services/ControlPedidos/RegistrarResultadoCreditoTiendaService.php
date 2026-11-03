<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Resultado de Wizerp informado por la auxiliar. No aprueba por el paso del tiempo ni pide transferencia. */
final class RegistrarResultadoCreditoTiendaService
{
    public const PENDIENTE_CORTE = 'Falta la hora de vencimiento del crédito. La cancelación automática permanece inactiva.';

    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private RegistrarPagoPedidoBmaService $pagos,
    ) {}

    public function ejecutar(PedidoBma $pedido, User $usuario, string $resultado, string $causa = ''): PedidoBma
    {
        if (! $usuario->can('control_pedidos.auditar')) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien audita registra el resultado de crédito.',
            ]);
        }

        $resultado = trim($resultado);
        $causa = trim($causa);
        if (! in_array($resultado, ['aprobado', 'bloqueado'], true)) {
            throw ValidationException::withMessages(['resultado' => 'Indique si el crédito quedó aprobado o bloqueado.']);
        }
        if ($resultado === 'bloqueado' && $causa === '') {
            throw ValidationException::withMessages(['causa' => 'Indique la causa del bloqueo.']);
        }

        return DB::transaction(function () use ($pedido, $usuario, $resultado, $causa) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $codigoCobertura = $pedido->codigoModalidadPreparacionVigente()
                ?: (is_array($pedido->solicitud_preparacion_json) ? ($pedido->solicitud_preparacion_json['codigo_modalidad'] ?? null) : null);
            if ($codigoCobertura !== CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO) {
                throw ValidationException::withMessages([
                    'modalidad' => 'Este pedido no está en cobertura de crédito.',
                ]);
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $json['credito_wizerp'] = [
                'resultado' => $resultado,
                'causa' => $causa !== '' ? $causa : null,
                'registrado_at' => now()->toIso8601String(),
                'registrado_por_id' => $usuario->id,
                'corte_cancelacion' => 'inactivo',
                'pendiente' => self::PENDIENTE_CORTE,
            ];
            $pedido->update(['solicitud_preparacion_json' => $json]);

            if ($resultado === 'aprobado' && ! $pedido->pagosExhibicion()->where('forma_pago', 'credito')->where('activo_para_cobertura', true)->exists()) {
                $monto = (float) ($pedido->total_a_cobrar ?? $pedido->total_mercancia ?? 0);
                if ($monto > 0) {
                    $this->pagos->handle($pedido, [
                        'monto' => $monto,
                        'forma_pago' => 'credito',
                        'referencia' => 'Wizerp aprobado',
                        'observaciones' => 'Cobertura de crédito. No es un comprobante de transferencia.',
                        'idempotency_key' => 'credito-wizerp:'.$pedido->id,
                    ], null, $usuario->id);
                }
            }

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            if ($estatusId) {
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    $resultado === 'aprobado'
                        ? 'Crédito aprobado en Wizerp. Falta la remisión. '.self::PENDIENTE_CORTE
                        : "Crédito bloqueado: {$causa}. ".self::PENDIENTE_CORTE,
                    AccionesHistorialPedidoBma::VALIDACION_PAGO
                );
            }

            return $pedido->fresh(['estatus', 'pagosExhibicion']);
        });
    }
}
