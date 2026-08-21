<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GestionarRemisionPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private AvanzarColaErroresPedidoBmaService $colaErroresService,
    ) {}

    public function subir(PedidoBma $pedido, UploadedFile $archivo, int $usuarioId): PedidoBma
    {
        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se puede adjuntar remisión en pedidos pendientes de revisión.');
        }

        if ($archivo->getMimeType() !== 'application/pdf' && !str_ends_with(strtolower($archivo->getClientOriginalName()), '.pdf')) {
            throw new \InvalidArgumentException('La remisión debe ser un archivo PDF.');
        }

        return DB::transaction(function () use ($pedido, $archivo, $usuarioId) {
            $this->eliminarRemisiones($pedido);

            $ruta = $archivo->store("pedidos_bma/remisiones/{$pedido->id}", 'public');
            $nombre = $archivo->getClientOriginalName();

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_REMISION,
                'ruta_archivo' => $ruta,
                'nombre_original' => $nombre,
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => 0,
            ]);

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                "Remisión adjuntada: {$nombre}",
                AccionesHistorialPedidoBma::CARGA_REMISION,
                ['ruta' => $ruta, 'nombre' => $nombre]
            );

            $this->resolverCamposAuxiliar($pedido, ['remision'], $usuarioId, "Remisión corregida: {$nombre}");

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
                'errores',
            ]);
        });
    }

    public function actualizarFolioRemision(PedidoBma $pedido, string $folio, int $usuarioId): PedidoBma
    {
        if (! $pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se puede corregir el folio en pedidos pendientes de revisión.');
        }

        $folio = trim($folio);
        if ($folio === '') {
            throw new \InvalidArgumentException('Indique el folio de pedido (Wizerp).');
        }

        return DB::transaction(function () use ($pedido, $folio, $usuarioId) {
            $antes = (string) ($pedido->folio_remision ?? '');
            $pedido->update(['folio_remision' => $folio]);

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                $antes !== '' && $antes !== $folio
                    ? "Folio de pedido corregido: {$antes} → {$folio}"
                    : "Folio de pedido actualizado: {$folio}",
                AccionesHistorialPedidoBma::CORRECCION
            );

            $this->resolverCamposAuxiliar($pedido, ['folio_remision'], $usuarioId, "Folio corregido: {$folio}");

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
                'errores',
            ]);
        });
    }

    /**
     * @param  list<string>  $campos
     */
    private function resolverCamposAuxiliar(PedidoBma $pedido, array $campos, int $usuarioId, string $correccion): void
    {
        $restantes = $this->colaErroresService->quitarCampos($pedido, $campos, $usuarioId, $correccion);
        if ($restantes === []) {
            $pedido->update($this->colaErroresService->attrsColaVacia());
        } else {
            $pedido->update($this->colaErroresService->attrsColaPendiente($restantes));
        }
    }

    public function eliminar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se puede eliminar la remisión en pedidos pendientes de revisión.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $remision = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->first();
            $nombre = $remision?->nombre_original;

            $this->eliminarRemisiones($pedido);

            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                $nombre ? "Remisión eliminada: {$nombre}" : 'Remisión eliminada.',
                AccionesHistorialPedidoBma::ELIMINA_REMISION
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
            ]);
        });
    }

    private function eliminarRemisiones(PedidoBma $pedido): void
    {
        $remisiones = $pedido->documentos()->where('tipo', PedidoBmaDocumento::TIPO_REMISION)->get();

        foreach ($remisiones as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }
}
