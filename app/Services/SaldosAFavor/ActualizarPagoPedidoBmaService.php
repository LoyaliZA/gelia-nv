<?php

namespace App\Services\SaldosAFavor;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class ActualizarPagoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historial,
        private RegistrarPagoPedidoBmaService $registrarPago,
    ) {}

    public function handle(
        PedidoBmaPago $pago,
        array $datos,
        ?UploadedFile $comprobante = null,
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

        $referencia = array_key_exists('referencia', $datos)
            ? ($datos['referencia'] ?: null)
            : $pago->referencia;

        $cambioMaterial = abs($monto - (float) $pago->monto) > 0.001
            || $forma !== $pago->forma_pago
            || (int) ($bancoId ?? 0) !== (int) ($pago->catalogo_banco_id ?? 0)
            || (string) ($referencia ?? '') !== (string) ($pago->referencia ?? '')
            || ($comprobante && $comprobante->isValid());

        return DB::transaction(function () use (
            $pago,
            $pedido,
            $datos,
            $comprobante,
            $usuarioId,
            $monto,
            $forma,
            $bancoId,
            $referencia,
            $cambioMaterial
        ) {
            $antesRevision = $pago->estado_revision;
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

            if ($comprobante && $comprobante->isValid()) {
                if ($pago->ruta_archivo) {
                    Storage::disk('public')->delete($pago->ruta_archivo);
                }
                $ruta = $comprobante->store("pedidos_bma/pagos/{$pedido->id}", 'public');
                $attrs['ruta_archivo'] = $ruta;
                $attrs['nombre_original'] = $comprobante->getClientOriginalName();
                $attrs['mime_type'] = $comprobante->getMimeType();
                $attrs['tamano_bytes'] = $comprobante->getSize();
            }

            $invalidaRevision = $cambioMaterial
                && $antesRevision !== PedidoBmaPago::REVISION_PENDIENTE;

            if ($invalidaRevision) {
                $attrs['estado_revision'] = PedidoBmaPago::REVISION_PENDIENTE;
                $attrs['revisado_por_id'] = null;
                $attrs['revisado_at'] = null;
            }

            $pago->update($attrs);

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
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    $comentario,
                    AccionesHistorialPedidoBma::EDICION_EXHIBICION_PAGO,
                    isset($attrs['ruta_archivo'])
                        ? ['ruta' => $attrs['ruta_archivo'], 'nombre' => $attrs['nombre_original'] ?? null]
                        : null
                );
            }

            $this->registrarPago->generarExcedenteSiAplica($pedido->fresh(), $usuarioId);

            return $pago->fresh(['banco']);
        });
    }
}
