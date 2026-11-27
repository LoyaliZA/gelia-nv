<?php

namespace App\Services\ControlPedidos;

use App\Events\ImpresionGuiasCedisActualizada;
use App\Models\ControlPedidos\ImpresionGuiaCedis;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegistrarGuiasComunicadasCedis
{
    public function porCaja(PedidoBma $pedido, iterable $documentos): void
    {
        $huboCambios = false;
        foreach ($documentos as $documento) {
            $caja = $pedido->cajas->firstWhere('id', $documento->pedido_bma_caja_id);
            $version = ImpresionGuiaCedis::query()->firstOrCreate(['documento_id' => $documento->id], [
                'pedido_bma_id' => $pedido->id,
                'pedido_bma_caja_id' => $caja->id,
                'orden_caja' => $caja->orden,
                'nombre_caja' => $caja->tipoCaja?->nombre,
                'nombre_pdf' => $documento->nombre_original,
                'comunicada_at' => now(),
            ]);
            $huboCambios = $huboCambios || $version->wasRecentlyCreated;
        }
        if ($huboCambios) {
            ImpresionGuiasCedisActualizada::dispatch();
        }
    }

    public function general(PedidoBma $pedido): void
    {
        $guia = $pedido->documentos()->vigente()
            ->where('tipo', PedidoBmaDocumento::TIPO_GUIA)->whereNull('pedido_bma_caja_id')
            ->reorder('id', 'desc')->first();
        if (! $guia || ! str_contains(strtolower((string) $guia->mime_type), 'pdf')
            || ImpresionGuiaCedis::query()->where('documento_id', $guia->id)->exists()) {
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
        $ruta = 'impresion_guias_cedis/'.$pedido->id.'/'.Str::uuid().'.pdf';
        try {
            if (! Storage::disk('local')->put($ruta, $archivo)) {
                throw new \RuntimeException('No se pudo conservar el PDF comunicado a CEDIS. Intente nuevamente.');
            }
            ImpresionGuiaCedis::query()->create([
                'pedido_bma_id' => $pedido->id,
                'documento_id' => $guia->id,
                'nombre_pdf' => $guia->nombre_original,
                'ruta_copia' => $ruta,
                'comunicada_at' => now(),
            ]);
            ImpresionGuiasCedisActualizada::dispatch();
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($ruta);
            throw $error;
        } finally {
            if (is_resource($archivo)) {
                fclose($archivo);
            }
        }
    }
}
