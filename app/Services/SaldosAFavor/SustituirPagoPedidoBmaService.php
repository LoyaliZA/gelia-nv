<?php

namespace App\Services\SaldosAFavor;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SustituirPagoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private RegistrarPagoPedidoBmaService $registrarPago,
    ) {}

    public function ejecutar(
        PedidoBmaPago $rechazado,
        array $datos,
        UploadedFile $comprobante,
        int $usuarioId,
    ): PedidoBmaPago {
        $pedido = $rechazado->pedido;
        if (! $pedido) {
            throw new RuntimeException('La exhibición no tiene pedido asociado.');
        }
        $pedido->loadMissing('estatus');

        if (! $pedido->puedeEditarExhibicionesPago()) {
            throw new RuntimeException('No se puede sustituir comprobantes en el estado actual del pedido.');
        }

        if ($rechazado->estado_revision !== PedidoBmaPago::REVISION_RECHAZADO) {
            throw new InvalidArgumentException('Solo se pueden sustituir exhibiciones rechazadas.');
        }

        if ($rechazado->sustituto()->exists()) {
            throw new InvalidArgumentException('Esta exhibición ya tiene un sustituto vigente.');
        }

        $monto = round((float) ($datos['monto'] ?? $rechazado->monto), 2);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto de la exhibición debe ser mayor a cero.');
        }

        $forma = $datos['forma_pago'] ?? $rechazado->forma_pago;
        if ($forma !== null && $forma !== '' && ! in_array($forma, PedidoBmaPago::FORMAS_PAGO, true)) {
            throw new InvalidArgumentException('Forma de pago no válida.');
        }

        $bancoId = array_key_exists('catalogo_banco_id', $datos)
            ? (isset($datos['catalogo_banco_id']) && $datos['catalogo_banco_id'] !== ''
                ? (int) $datos['catalogo_banco_id']
                : null)
            : $rechazado->catalogo_banco_id;

        if (PedidoBmaPago::formaRequiereBanco($forma) && ! $bancoId) {
            throw new InvalidArgumentException('Esta forma de pago requiere banco receptor.');
        }
        if (! PedidoBmaPago::formaRequiereBanco($forma)) {
            $bancoId = null;
        }

        CoberturaPagoPedidoBmaService::assertBancoPermitido($pedido, $bancoId);

        if (! $comprobante->isValid()) {
            throw new InvalidArgumentException('Debe adjuntar el nuevo comprobante.');
        }

        $ruta = null;
        try {
            $ruta = $comprobante->store("pedidos_bma/pagos/{$pedido->id}", 'public');

            return DB::transaction(function () use (
                $rechazado, $pedido, $datos, $comprobante, $usuarioId, $monto, $forma, $bancoId, $ruta
            ) {
                $rechazado = PedidoBmaPago::query()->lockForUpdate()->findOrFail($rechazado->id);
                if ($rechazado->sustituto()->exists()) {
                    throw new InvalidArgumentException('Esta exhibición ya tiene un sustituto vigente.');
                }

                $siguiente = ((int) PedidoBmaPago::where('pedido_bma_id', $pedido->id)->max('numero_exhibicion')) + 1;

                $nuevo = PedidoBmaPago::create([
                    'pedido_bma_id' => $pedido->id,
                    'reemplaza_pago_id' => $rechazado->id,
                    'numero_exhibicion' => $siguiente,
                    'monto' => $monto,
                    'catalogo_banco_id' => $bancoId,
                    'forma_pago' => $forma,
                    'fecha_pago' => $datos['fecha_pago'] ?? now(),
                    'referencia' => $datos['referencia'] ?? $rechazado->referencia,
                    'capturado_por_id' => $usuarioId,
                    'estado_revision' => PedidoBmaPago::REVISION_PENDIENTE,
                    'activo_para_cobertura' => true,
                    'observaciones' => $datos['observaciones'] ?? null,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $comprobante->getClientOriginalName(),
                    'mime_type' => $comprobante->getMimeType(),
                    'tamano_bytes' => $comprobante->getSize(),
                ]);

                $rechazado->update([
                    'activo_para_cobertura' => false,
                    'sustituido_at' => now(),
                ]);

                $this->historial->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    sprintf(
                        'Exhibición #%d sustituye a #%d ($%s). El comprobante anterior se conserva.',
                        $siguiente,
                        $rechazado->numero_exhibicion,
                        number_format($monto, 2, '.', ',')
                    ),
                    AccionesHistorialPedidoBma::SUSTITUCION_EXHIBICION_PAGO,
                    ['ruta' => $ruta, 'nombre' => $comprobante->getClientOriginalName(), 'reemplaza_pago_id' => $rechazado->id]
                );

                $this->registrarPago->reconciliarExcedenteTrasExhibicion($pedido->fresh(), $usuarioId);

                return $nuevo->fresh(['banco', 'reemplaza']);
            });
        } catch (Throwable $e) {
            if ($ruta) {
                try {
                    Storage::disk('public')->delete($ruta);
                } catch (Throwable $cleanup) {
                    Log::warning('Archivo huérfano de sustitución de pago', [
                        'ruta' => $ruta,
                        'error' => $cleanup->getMessage(),
                    ]);
                }
            }
            throw $e;
        }
    }
}
