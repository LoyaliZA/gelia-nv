<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\SaldosAFavor\PedidoBmaPagoArchivo;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class AdjuntarComprobantesPagoPedidoBma
{
    /**
     * @param  UploadedFile|array<int, mixed>|null  $entrada
     * @return list<UploadedFile>
     */
    public function normalizar(UploadedFile|array|null $entrada): array
    {
        if ($entrada instanceof UploadedFile) {
            $entrada = [$entrada];
        }
        if (! is_array($entrada)) {
            return [];
        }

        $validos = [];
        foreach ($entrada as $archivo) {
            if ($archivo instanceof UploadedFile && $archivo->isValid()) {
                $validos[] = $archivo;
            }
        }

        return $validos;
    }

    public function cantidadActual(PedidoBmaPago $pago): int
    {
        $hijos = $pago->relationLoaded('archivos')
            ? $pago->archivos->count()
            : $pago->archivos()->count();

        if ($hijos > 0) {
            return $hijos;
        }

        return filled($pago->ruta_archivo) ? 1 : 0;
    }

    /**
     * Guarda archivos nuevos. El primero del pago permanece en las columnas del pago.
     * Si el pago solo tenía el archivo legado, lo registra como orden 1 sin copiar el disco.
     *
     * @param  list<UploadedFile>  $archivos
     * @return list<string> rutas nuevas en disco
     */
    public function guardar(PedidoBmaPago $pago, array $archivos): array
    {
        if ($archivos === []) {
            return [];
        }

        $total = $this->cantidadActual($pago) + count($archivos);
        if ($total > PedidoBmaPago::MAX_COMPROBANTES) {
            throw new InvalidArgumentException(
                'Puede adjuntar hasta '.PedidoBmaPago::MAX_COMPROBANTES.' comprobantes por exhibición.'
            );
        }

        $this->materializarLegado($pago);

        $orden = (int) $pago->archivos()->max('orden');
        $rutasNuevas = [];
        $primeroNuevo = null;

        foreach ($archivos as $archivo) {
            $orden++;
            $ruta = $archivo->store("pedidos_bma/pagos/{$pago->pedido_bma_id}", 'public');
            $rutasNuevas[] = $ruta;
            $fila = PedidoBmaPagoArchivo::query()->create([
                'pedido_bma_pago_id' => $pago->id,
                'orden' => $orden,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
            ]);
            $primeroNuevo ??= $fila;
        }

        if (empty($pago->ruta_archivo) && $primeroNuevo) {
            $pago->update([
                'ruta_archivo' => $primeroNuevo->ruta_archivo,
                'nombre_original' => $primeroNuevo->nombre_original,
                'mime_type' => $primeroNuevo->mime_type,
                'tamano_bytes' => $primeroNuevo->tamano_bytes,
            ]);
        }

        $pago->unsetRelation('archivos');

        return $rutasNuevas;
    }

    private function materializarLegado(PedidoBmaPago $pago): void
    {
        if (! filled($pago->ruta_archivo) || $pago->archivos()->exists()) {
            return;
        }

        PedidoBmaPagoArchivo::query()->create([
            'pedido_bma_pago_id' => $pago->id,
            'orden' => 1,
            'ruta_archivo' => $pago->ruta_archivo,
            'nombre_original' => $pago->nombre_original,
            'mime_type' => $pago->mime_type,
            'tamano_bytes' => $pago->tamano_bytes,
        ]);
    }
}
