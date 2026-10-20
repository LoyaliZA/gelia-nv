<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\SnapshotHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ActualizarPagoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private RegistrarPagoPedidoBmaService $registrarPago,
        private AdjuntarComprobantesPagoPedidoBma $comprobantes,
    ) {}

    public function handle(
        PedidoBmaPago $pago,
        array $datos,
        UploadedFile|array|null $comprobante = null,
        ?int $usuarioId = null,
    ): PedidoBmaPago {
        $pedido = $pago->pedido;
        if (! $pedido) {
            throw new RuntimeException('La exhibición no tiene pedido asociado.');
        }
        $pedido->loadMissing('estatus');

        if (! $pedido->puedeEditarExhibicionesPago()) {
            throw new RuntimeException('No se puede editar exhibiciones en el estado actual del pedido.');
        }

        if (! $pago->esEditableBorrador()) {
            throw new RuntimeException(
                'No se puede editar una exhibición revisada o rechazada. Use sustitución de comprobante.'
            );
        }

        $archivos = $this->comprobantes->normalizar($comprobante);
        $cantidadTrasAgregar = $this->comprobantes->cantidadActual($pago) + count($archivos);
        if ($cantidadTrasAgregar > PedidoBmaPago::MAX_COMPROBANTES) {
            throw new InvalidArgumentException(
                'Puede adjuntar hasta '.PedidoBmaPago::MAX_COMPROBANTES.' comprobantes por exhibición.'
            );
        }

        $monto = array_key_exists('monto', $datos)
            ? round((float) $datos['monto'], 2)
            : (float) $pago->monto;
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto de la exhibición debe ser mayor a cero.');
        }

        $forma = array_key_exists('forma_pago', $datos) ? ($datos['forma_pago'] ?: null) : $pago->forma_pago;
        if ($forma !== null && $forma !== '' && ! in_array($forma, PedidoBmaPago::FORMAS_PAGO, true)) {
            throw new InvalidArgumentException('Forma de pago no válida.');
        }

        $bancoId = array_key_exists('catalogo_banco_id', $datos)
            ? (isset($datos['catalogo_banco_id']) && $datos['catalogo_banco_id'] !== ''
                ? (int) $datos['catalogo_banco_id']
                : null)
            : $pago->catalogo_banco_id;

        if (PedidoBmaPago::formaRequiereBanco($forma) && ! $bancoId) {
            throw new InvalidArgumentException('Esta forma de pago requiere banco receptor.');
        }
        if (! PedidoBmaPago::formaRequiereBanco($forma)) {
            $bancoId = null;
        }

        if (PedidoBmaPago::formaRequiereComprobante($forma) && $cantidadTrasAgregar === 0) {
            throw new InvalidArgumentException('Cada exhibición de pago debe incluir su comprobante.');
        }

        CoberturaPagoPedidoBmaService::assertBancoPermitido($pedido, $bancoId);

        $referencia = array_key_exists('referencia', $datos)
            ? ($datos['referencia'] ?: null)
            : $pago->referencia;

        $cambioMaterial = abs($monto - (float) $pago->monto) > 0.001
            || $forma !== $pago->forma_pago
            || (int) ($bancoId ?? 0) !== (int) ($pago->catalogo_banco_id ?? 0)
            || (string) ($referencia ?? '') !== (string) ($pago->referencia ?? '')
            || $archivos !== [];

        $rutasNuevas = [];
        try {
            return DB::transaction(function () use (
                $pago,
                $pedido,
                $datos,
                $archivos,
                $usuarioId,
                $monto,
                $forma,
                $bancoId,
                $referencia,
                $cambioMaterial,
                &$rutasNuevas
            ) {
                $antesRevision = $pago->estado_revision;
                $montoAnterior = (float) $pago->monto;
                $attrs = [
                    'monto' => $monto,
                    'forma_pago' => $forma,
                    'catalogo_banco_id' => $bancoId,
                    'referencia' => $referencia,
                ];

                if (array_key_exists('fecha_pago', $datos) && $datos['fecha_pago']) {
                    $attrs['fecha_pago'] = $datos['fecha_pago'];
                }
                if (array_key_exists('observaciones', $datos)) {
                    $attrs['observaciones'] = $datos['observaciones'];
                }

                $invalidaRevision = $cambioMaterial
                    && $antesRevision !== PedidoBmaPago::REVISION_PENDIENTE;

                if ($invalidaRevision) {
                    $attrs['estado_revision'] = PedidoBmaPago::REVISION_PENDIENTE;
                    $attrs['revisado_por_id'] = null;
                    $attrs['revisado_at'] = null;
                }

                $pago->update($attrs);
                $rutasNuevas = $this->comprobantes->guardar($pago, $archivos);
                $pago = $pago->fresh(['banco', 'archivos']);

                if ($usuarioId) {
                    $comentario = sprintf(
                        'Exhibición #%d actualizada ($%s, %s).',
                        $pago->numero_exhibicion,
                        number_format($monto, 2, '.', ','),
                        PedidoBmaPago::labelForma($forma) ?? 'sin método'
                    );
                    if ($invalidaRevision) {
                        $comentario .= sprintf(' Revisión %s → pendiente.', $antesRevision);
                    }
                    $pedidoFresh = $pedido->fresh();
                    $snapshot = SnapshotHistorialPedidoBma::merge(
                        SnapshotHistorialPedidoBma::exhibicion($pago, $pedidoFresh),
                        [
                            'cambios' => [
                                'monto_anterior' => number_format($montoAnterior, 2, '.', ''),
                                'monto_nuevo' => number_format($monto, 2, '.', ''),
                            ],
                        ]
                    );
                    $this->historial->ejecutar(
                        $pedido->id,
                        $usuarioId,
                        $pedido->catalogo_estatus_pedido_id,
                        $pedido->catalogo_estatus_pedido_id,
                        $comentario,
                        AccionesHistorialPedidoBma::EDICION_EXHIBICION_PAGO,
                        $rutasNuevas !== []
                            ? ['ruta' => $rutasNuevas[0], 'nombre' => $archivos[0]->getClientOriginalName()]
                            : null,
                        $snapshot
                    );
                }

                $this->registrarPago->reconciliarExcedenteTrasExhibicion($pedido->fresh(), $usuarioId);

                return $pago;
            });
        } catch (Throwable $e) {
            foreach ($rutasNuevas as $rutaNueva) {
                try {
                    Storage::disk('public')->delete($rutaNueva);
                } catch (Throwable $cleanup) {
                    Log::warning('Archivo huérfano al actualizar pago', [
                        'ruta' => $rutaNueva,
                        'error' => $cleanup->getMessage(),
                    ]);
                }
            }
            throw $e;
        }
    }
}
