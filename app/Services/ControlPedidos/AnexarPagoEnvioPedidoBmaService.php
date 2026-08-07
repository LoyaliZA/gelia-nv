<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaAnexoEnvio;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class AnexarPagoEnvioPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, array $datos, UploadedFile $comprobante, int $usuarioId): PedidoBmaAnexoEnvio
    {
        if (!$pedido->puedeAnexarPagoEnvio()) {
            throw new \RuntimeException('Este pedido no admite anexar pago de envío en su estado actual.');
        }

        $monto = (float) ($datos['monto'] ?? 0);
        if ($monto <= 0) {
            throw new \InvalidArgumentException('El monto del envío debe ser mayor que cero.');
        }

        if (!$comprobante->isValid()) {
            throw new \InvalidArgumentException('El comprobante de envío no es válido.');
        }

        if ($pedido->anexosEnvio()->where('estatus', PedidoBmaAnexoEnvio::ESTATUS_PENDIENTE)->exists()) {
            throw new \RuntimeException('Ya existe un anexo de envío pendiente de revisión.');
        }

        return DB::transaction(function () use ($pedido, $datos, $comprobante, $usuarioId, $monto) {
            $ruta = $comprobante->store("pedidos_bma/anexos_envio/{$pedido->id}", 'public');

            $anexo = $pedido->anexosEnvio()->create([
                'monto' => $monto,
                'catalogo_banco_id' => $datos['catalogo_banco_id'] ?? null,
                'comentarios' => $datos['comentarios'] ?? null,
                'ruta_archivo' => $ruta,
                'nombre_original' => $comprobante->getClientOriginalName(),
                'mime_type' => $comprobante->getMimeType(),
                'tamano_bytes' => $comprobante->getSize(),
                'estatus' => PedidoBmaAnexoEnvio::ESTATUS_PENDIENTE,
                'registrado_por_id' => $usuarioId,
            ]);

            $pedido->update([
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_REVISION_ANEXO,
            ]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                sprintf(
                    'Anexo de pago de envío por $%s. Pendiente de revisión del auxiliar.',
                    number_format($monto, 2, '.', ',')
                ),
                AccionesHistorialPedidoBma::ANEXO_PAGO_ENVIO
            );

            return $anexo->load(['banco', 'registradoPor']);
        });
    }
}
