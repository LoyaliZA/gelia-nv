<?php

namespace App\Services\ControlPedidos;

use App\Events\ImpresionGuiasCedisActualizada;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\Storage;

class RegistrarGuiasComunicadasCedis
{
    public function __construct(private RegistrarHistorialPedidoService $historial) {}

    public function porCaja(PedidoBma $pedido, int $usuarioId, iterable $documentos): void
    {
        $guias = [];
        foreach ($documentos as $documento) {
            $caja = $pedido->cajas->firstWhere('id', $documento->pedido_bma_caja_id);
            $guias[$documento->id] = [
                'caja_id' => $caja->id,
                'orden' => $caja->orden,
                'caja' => $caja->tipoCaja?->nombre ?: 'Caja '.($caja->orden + 1),
                'nombre' => $documento->nombre_original,
            ];
        }
        if ($guias !== []) {
            $this->registrar($pedido, $usuarioId, $guias);
        }
    }

    public function general(PedidoBma $pedido, int $usuarioId): void
    {
        $guia = $pedido->documentos()->vigente()
            ->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->whereNull('pedido_bma_caja_id')
            ->reorder('id', 'desc')->first();
        if (! $guia || ! str_contains(strtolower((string) $guia->mime_type), 'pdf')
            || $pedido->historial()->where('accion', AccionesHistorialPedidoBma::GUIAS_COMUNICADAS_CEDIS)
                ->whereJsonContains('snapshot_json->documentos', $guia->id)->exists()) {
            return;
        }

        $origen = Storage::disk($guia->discoArchivo());
        if (! $origen->exists($guia->ruta_archivo)) {
            return;
        }
        $archivo = $origen->readStream($guia->ruta_archivo);
        if (! is_resource($archivo)) {
            throw new \RuntimeException('No se pudo conservar el PDF comunicado a CEDIS. Intente nuevamente.');
        }
        // Las guías generales pueden perder el original al ser reemplazadas.
        $ruta = "impresion_guias_cedis/{$pedido->id}/{$guia->id}.pdf";
        try {
            if (! Storage::disk('local')->put($ruta, $archivo)) {
                throw new \RuntimeException('No se pudo conservar el PDF comunicado a CEDIS. Intente nuevamente.');
            }
            $this->registrar($pedido, $usuarioId, [
                $guia->id => [
                    'caja_id' => null,
                    'orden' => null,
                    'caja' => 'Guía general',
                    'nombre' => $guia->nombre_original,
                    'copia' => $ruta,
                ],
            ]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($ruta);
            throw $error;
        } finally {
            if (is_resource($archivo)) {
                fclose($archivo);
            }
        }
    }

    private function registrar(PedidoBma $pedido, int $usuarioId, array $guias): void
    {
        $this->historial->ejecutar(
            $pedido->id,
            $usuarioId,
            $pedido->catalogo_estatus_pedido_id,
            $pedido->catalogo_estatus_pedido_id,
            'Comunicó los PDF de guía a CEDIS.',
            AccionesHistorialPedidoBma::GUIAS_COMUNICADAS_CEDIS,
            snapshot: ['documentos' => array_keys($guias), 'guias' => $guias],
        );
        ImpresionGuiasCedisActualizada::dispatch();
    }
}
