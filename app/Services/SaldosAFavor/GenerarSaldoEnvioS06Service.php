<?php

namespace App\Services\SaldosAFavor;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\Municipal\CambioMunicipalATienda;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * S-06: Administración reconoce el transporte pagado y no utilizado de un envío que cambió a recolección en sucursal.
 * El saldo nace «solo transporte» con origen económico comprobado; se aplica únicamente a gastos de envío de pedidos
 * posteriores del mismo cliente. El importe lo fija Administración con sus antecedentes: no hay fórmula ni mínimo
 * automático (el criterio de $10 sigue pendiente de negocio y aquí no se aplica ni se sustituye). Autorizar no libera
 * pagos ni mueve mercancía.
 */
final class GenerarSaldoEnvioS06Service
{
    public const MOTIVO_CODIGO = 'envio_no_utilizado';

    public const FUENTE = 's06_autorizado';

    public function __construct(
        private GenerarCreditoSafService $generar,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /** @param  list<UploadedFile>  $evidencias */
    public function ejecutar(PedidoBma $pedido, User $admin, float $monto, string $detalle, array $evidencias): SafCredito
    {
        if (! $admin->can('saldos_favor.autorizar_envio')) {
            throw ValidationException::withMessages(['permiso' => 'Solo Administración autoriza un saldo de envío.']);
        }
        $monto = round($monto, 2);
        $detalle = trim($detalle);
        if ($monto <= 0) {
            throw ValidationException::withMessages(['monto' => 'Indique el importe de transporte no utilizado que autoriza.']);
        }
        if (mb_strlen($detalle) < 10) {
            throw ValidationException::withMessages(['detalle' => 'Describa los antecedentes del transporte pagado y no utilizado.']);
        }
        if (array_values(array_filter($evidencias, fn ($a) => $a instanceof UploadedFile)) === []) {
            throw ValidationException::withMessages(['evidencias' => 'Adjunte la evidencia del transporte pagado.']);
        }

        return DB::transaction(function () use ($pedido, $admin, $monto, $detalle, $evidencias) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $cambio = CambioMunicipalATienda::datos($pedido);
            if (($cambio['estado'] ?? null) !== CambioMunicipalATienda::ESTADO_APLICADO) {
                throw ValidationException::withMessages(['pedido' => 'El cambio a recolección en sucursal debe estar verificado antes de autorizar el saldo de envío.']);
            }
            if (is_array($cambio['saldo_envio'] ?? null)) {
                throw ValidationException::withMessages(['pedido' => 'Este cambio ya tiene un saldo de envío autorizado.']);
            }
            if (! $pedido->cliente_id || $pedido->pago_validado_at === null) {
                throw ValidationException::withMessages(['pedido' => 'El envío debe tener cliente y pago verificado.']);
            }
            // Quien registró la venta o solicitó el cambio no autoriza su propio saldo.
            if (in_array((int) $admin->id, [(int) $pedido->vendedor_id, (int) ($cambio['solicitado_por_id'] ?? 0)], true)) {
                throw ValidationException::withMessages(['permiso' => 'Quien registró la venta o solicitó el cambio no autoriza el saldo de envío.']);
            }
            $motivo = SafMotivo::query()->where('codigo', self::MOTIVO_CODIGO)->where('activo', true)->first();
            if (! $motivo) {
                throw ValidationException::withMessages(['motivo' => 'Falta el motivo «Envío no utilizado» en el catálogo.']);
            }

            try {
                $credito = $this->generar->handle([
                    'cliente_id' => (int) $pedido->cliente_id,
                    'monto' => $monto,
                    'saf_motivo_id' => $motivo->id,
                    'detalle_motivo' => $detalle,
                    'canal_origen' => 'bellaroma',
                    'pedido_bma_id' => $pedido->id,
                    'documento_origen' => $pedido->folio_remision ?: $pedido->folio,
                    'generado_por_id' => $admin->id,
                    'origen_manual' => true,
                    // S-06: el mínimo general de saldos a favor no se aplica automáticamente; su criterio sigue pendiente de negocio.
                    'omitir_monto_minimo' => true,
                    'clasificacion' => [
                        'uso' => SafCredito::USO_SOLO_TRANSPORTE,
                        'origen' => SafCredito::ORIGEN_TRANSPORTE_CONFIRMADO,
                        'fuente' => self::FUENTE,
                        'motivo' => $detalle,
                    ],
                ], $evidencias);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['monto' => $e->getMessage()]);
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $json[CambioMunicipalATienda::CLAVE]['saldo_envio'] = [
                'credito_id' => $credito->id,
                'folio' => $credito->folio,
                'monto' => $monto,
                'autorizado_por_id' => $admin->id,
                'autorizado_at' => now()->toIso8601String(),
            ];
            $pedido->update(['solicitud_preparacion_json' => $json]);

            $estatusId = (int) $pedido->catalogo_estatus_pedido_id;
            $this->historial->ejecutar(
                $pedido->id,
                $admin->id,
                $estatusId,
                $estatusId,
                "Administración autorizó un saldo de envío ({$credito->folio}) solo para gastos de transporte.",
                'saldo_envio_autorizado'
            );

            return $credito;
        });
    }
}
