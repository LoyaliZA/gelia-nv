<?php

namespace App\Services\Traspasos;

use App\Models\AuditoriaSolicitudTraspaso;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTraspaso;
use App\Models\SolicitudTraspasoDetalleDano;
use App\Models\SolicitudTraspasoProducto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportarDetalleDanoTraspasoService
{
    public function __construct(
        private NotificarTraspasoService $notificar
    ) {}

    /**
     * @param  list<UploadedFile>  $fotos
     */
    public function ejecutar(
        SolicitudTraspaso $solicitud,
        User $usuario,
        int $productoLineaId,
        string $motivo,
        array $fotos
    ): SolicitudTraspaso {
        return DB::transaction(function () use ($solicitud, $usuario, $productoLineaId, $motivo, $fotos) {
            $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
            if ($idRespondida === null || (int) $solicitud->catalogo_estado_solicitud_id !== $idRespondida) {
                abort(422, 'Solo se puede reportar detalle/daño en solicitudes Respondidas.');
            }

            /** @var SolicitudTraspasoProducto|null $linea */
            $linea = $solicitud->productos()->where('id', $productoLineaId)->first();
            if (! $linea) {
                abort(422, 'El producto no pertenece a esta solicitud.');
            }

            if ($fotos === []) {
                abort(422, 'Debe adjuntar al menos una fotografía.');
            }

            $existente = SolicitudTraspasoDetalleDano::query()
                ->where('solicitud_traspaso_producto_id', $linea->id)
                ->first();

            foreach ($existente?->paths ?? [] as $rutaAnterior) {
                Storage::disk('public')->delete($rutaAnterior);
            }

            $paths = [];
            foreach ($fotos as $foto) {
                if ($foto instanceof UploadedFile && $foto->isValid()) {
                    $paths[] = $foto->store("traspasos/detalles-dano/{$solicitud->id}/{$linea->id}", 'public');
                }
            }

            if ($paths === []) {
                abort(422, 'No se pudo guardar ninguna fotografía.');
            }

            $payload = [
                'solicitud_traspaso_id' => $solicitud->id,
                'solicitud_traspaso_producto_id' => $linea->id,
                'motivo' => $motivo,
                'paths' => $paths,
                'reportado_por_id' => $usuario->id,
                'reportado_at' => now(),
            ];

            if ($existente) {
                $existente->update($payload);
            } else {
                SolicitudTraspasoDetalleDano::create($payload);
            }

            AuditoriaSolicitudTraspaso::create([
                'solicitud_traspaso_id' => $solicitud->id,
                'usuario_id' => $usuario->id,
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                'motivo_reporte' => "CEDIS reportó detalle/daño en {$linea->sku}: {$motivo}",
                'datos_snapshot' => [
                    'solicitud_traspaso_producto_id' => $linea->id,
                    'sku' => $linea->sku,
                    'descripcion' => $linea->descripcion,
                    'detalle_dano_paths' => $paths,
                    'detalle_dano_motivo' => $motivo,
                ],
            ]);

            $fresh = $solicitud->fresh([
                'vendedor',
                'estado',
                'cliente',
                'productos.detalleDano.reportadoPor',
            ]);
            $this->notificar->detalleDanoCedis($fresh, $usuario->id);

            return $fresh;
        });
    }
}
