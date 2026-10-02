<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Support\ControlPedidos\PoliticaSalidaPreparacion;
use App\Support\ControlPedidos\SalidaMunicipalPreparacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmarPagoSalidaPreparacionService
{
    public function __construct(
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $condicion,
        ?int $pedidoBmaPagoId = null,
        ?string $folioOperacion = null,
        ?int $versionEsperada = null,
    ): PedidoBmaCumplimientoFisico {
        if (! $usuario->can('control_pedidos.salida.validar_pago')) {
            throw ValidationException::withMessages([
                'permiso' => 'No tiene permiso para validar el pago. Tienda no registra el cobro.',
            ]);
        }

        $tarea->loadMissing('pedido');
        $pedido = $tarea->pedido;
        if (! $pedido) {
            throw ValidationException::withMessages([
                'pedido' => 'La tarea no tiene expediente.',
            ]);
        }

        if ((int) $pedido->vendedor_id === (int) $usuario->id) {
            throw ValidationException::withMessages([
                'permiso' => 'Quien registró la venta no puede validar su propio pago.',
            ]);
        }

        $politica = PoliticaSalidaPreparacion::desdeTarea($tarea);
        if (! $politica->usaSalidaInterna()) {
            throw ValidationException::withMessages([
                'modalidad' => 'Esta modalidad no usa validación de pago interna en Tienda.',
            ]);
        }

        if ($politica->esEnvioMunicipio()) {
            SalidaMunicipalPreparacion::assertCaratulaColocada($tarea);
            if ($condicion === PoliticaSalidaPreparacion::COBRO_POR_COBRAR
                && strtoupper((string) $tarea->modalidad_cobro) !== PoliticaSalidaPreparacion::COBRO_POR_COBRAR) {
                throw ValidationException::withMessages([
                    'condicion_cobro' => 'El expediente no está autorizado como envío por cobrar.',
                ]);
            }
        }

        if (! in_array($condicion, [PoliticaSalidaPreparacion::COBRO_PAGADO, PoliticaSalidaPreparacion::COBRO_POR_COBRAR], true)) {
            throw ValidationException::withMessages([
                'condicion_cobro' => 'Indique si el cobro está pagado o es por cobrar.',
            ]);
        }

        if ($condicion === PoliticaSalidaPreparacion::COBRO_POR_COBRAR && ! $politica->permitePorCobrar()) {
            throw ValidationException::withMessages([
                'condicion_cobro' => 'La transferencia diferida solo se libera después del pago.',
            ]);
        }

        $folioOperacion = $folioOperacion !== null ? trim($folioOperacion) : null;
        if ($condicion === PoliticaSalidaPreparacion::COBRO_PAGADO && ($folioOperacion === null || $folioOperacion === '') && ! $pedidoBmaPagoId) {
            throw ValidationException::withMessages([
                'folio_operacion' => 'Indique el folio de la operación o el cobro interno.',
            ]);
        }

        if ($pedidoBmaPagoId) {
            $pago = PedidoBmaPago::query()->whereKey($pedidoBmaPagoId)->first();
            if (! $pago || (int) $pago->pedido_bma_id !== (int) $pedido->id) {
                throw ValidationException::withMessages([
                    'pedido_bma_pago_id' => 'El cobro no pertenece a este expediente.',
                ]);
            }
        }

        return DB::transaction(function () use ($tarea, $usuario, $condicion, $pedidoBmaPagoId, $folioOperacion, $versionEsperada) {
            $this->asegurar->ejecutar($tarea);
            $cumplimiento = PedidoBmaCumplimientoFisico::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->lockForUpdate()
                ->firstOrFail();

            $clave = 'pago:'.$tarea->id;
            $ya = PedidoBmaCumplimientoEvento::query()->where('idempotencia_clave', $clave)->first();
            if ($ya) {
                return $cumplimiento;
            }

            if (in_array($cumplimiento->estado, [
                PedidoBmaCumplimientoFisico::ESTADO_ENTREGADA,
                PedidoBmaCumplimientoFisico::ESTADO_DEVOLUCION_PENDIENTE,
                PedidoBmaCumplimientoFisico::ESTADO_DEVUELTA_ANAQUEL,
            ], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'Este apartado ya no admite validación de pago.',
                ]);
            }

            if ($versionEsperada !== null && (int) $cumplimiento->version !== $versionEsperada) {
                throw ValidationException::withMessages([
                    'version' => 'Otra persona modificó este apartado. Actualice la página e intente de nuevo.',
                ]);
            }

            $cumplimiento->update([
                'condicion_cobro' => $condicion,
                'pago_confirmado_at' => now(),
                'pago_confirmado_por_id' => $usuario->id,
                'pedido_bma_pago_id' => $pedidoBmaPagoId,
                'folio_operacion' => $folioOperacion !== '' ? $folioOperacion : null,
                'version' => $cumplimiento->version + 1,
            ]);

            $this->eventos->ejecutar(
                $cumplimiento,
                PedidoBmaCumplimientoEvento::TIPO_PAGO_CONFIRMADO,
                $usuario,
                $clave,
                $condicion === PoliticaSalidaPreparacion::COBRO_POR_COBRAR
                    ? 'Salida autorizable por cobrar.'
                    : 'Pago validado para la salida.',
                [
                    'condicion_cobro' => $condicion,
                    'pedido_bma_pago_id' => $pedidoBmaPagoId,
                    'folio_operacion' => $folioOperacion,
                    'pedido_bma_id' => (int) $tarea->pedido_bma_id,
                ]
            );

            return $cumplimiento->fresh();
        });
    }
}
