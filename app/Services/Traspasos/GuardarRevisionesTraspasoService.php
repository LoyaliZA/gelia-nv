<?php

namespace App\Services\Traspasos;

use App\Models\SolicitudTraspaso;
use App\Models\SolicitudTraspasoProducto;
use App\Models\SolicitudTraspasoRevisionProducto;
use App\Models\User;
use App\Support\RevisionFisicaProducto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GuardarRevisionesTraspasoService
{
    /**
     * @param  list<array<string, mixed>>  $revisionesInput
     * @return list<array<string, mixed>>
     */
    public function normalizarRevisiones(SolicitudTraspaso $solicitud, array $revisionesInput): array
    {
        $lineas = $solicitud->productos()->get()->keyBy('id');
        $lineasPorProducto = $solicitud->productos()->get()->keyBy('producto_id');
        $conteoPorLinea = [];

        $out = [];
        foreach ($revisionesInput as $i => $rev) {
            $estado = (string) ($rev['estado_fisico'] ?? '');
            if (! in_array($estado, RevisionFisicaProducto::ESTADOS, true)) {
                throw ValidationException::withMessages([
                    "revisiones.{$i}.estado_fisico" => 'Estado físico no válido.',
                ]);
            }

            $lineaId = isset($rev['solicitud_traspaso_producto_id'])
                ? (int) $rev['solicitud_traspaso_producto_id']
                : 0;
            $productoId = isset($rev['producto_id']) && $rev['producto_id'] !== ''
                ? (int) $rev['producto_id']
                : null;

            /** @var SolicitudTraspasoProducto|null $linea */
            $linea = $lineaId > 0 ? $lineas->get($lineaId) : null;
            if (! $linea && $productoId) {
                $linea = $lineasPorProducto->get($productoId);
            }

            if (! $linea) {
                throw ValidationException::withMessages([
                    "revisiones.{$i}.producto_id" => 'El producto no pertenece a esta solicitud.',
                ]);
            }

            $conteoPorLinea[$linea->id] = ($conteoPorLinea[$linea->id] ?? 0) + 1;
            if ($conteoPorLinea[$linea->id] > (int) $linea->piezas) {
                throw ValidationException::withMessages([
                    "revisiones.{$i}.producto_id" => "Excede las {$linea->piezas} piezas solicitadas para {$linea->sku}.",
                ]);
            }

            $desc = trim((string) ($rev['descripcion_producto'] ?? ''));
            if ($desc === '') {
                $desc = "{$linea->sku} — {$linea->descripcion}";
            }

            $comentario = trim((string) ($rev['comentario'] ?? ''));
            if (RevisionFisicaProducto::requiereComentario($estado) && $comentario === '') {
                throw ValidationException::withMessages([
                    "revisiones.{$i}.comentario" => 'Comentario obligatorio para este estado.',
                ]);
            }

            /** @var list<UploadedFile> $evidencias */
            $evidencias = array_values(array_filter(
                $rev['evidencias'] ?? [],
                fn ($f) => $f instanceof UploadedFile && $f->isValid()
            ));

            $pathsRemotos = $rev['evidencia_paths_remotas'] ?? [];
            if (! is_array($pathsRemotos)) {
                $pathsRemotos = [];
            }

            if (RevisionFisicaProducto::requiereEvidencia($estado)
                && $evidencias === []
                && $pathsRemotos === []) {
                throw ValidationException::withMessages([
                    "revisiones.{$i}.evidencias" => 'Debe adjuntar evidencia para estado malo/dañado.',
                ]);
            }

            $out[] = [
                'solicitud_traspaso_producto_id' => $linea->id,
                'producto_id' => $linea->producto_id,
                'sku' => $linea->sku,
                'descripcion_producto' => mb_substr($desc, 0, 255),
                'estado_fisico' => $estado,
                'comentario' => $comentario !== '' ? $comentario : null,
                'unica_pieza' => filter_var($rev['unica_pieza'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'mejor_ejemplar' => filter_var($rev['mejor_ejemplar'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'evidencias' => $evidencias,
                'evidencia_paths_remotas' => array_values(array_filter($pathsRemotos, 'is_string')),
            ];
        }

        $totalPiezas = (int) $solicitud->productos()->sum('piezas');
        if (count($out) !== $totalPiezas) {
            throw ValidationException::withMessages([
                'revisiones' => "Debe registrar exactamente {$totalPiezas} pieza(s) revisada(s).",
            ]);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $revisionesNormalizadas
     */
    public function persistir(
        SolicitudTraspaso $solicitud,
        string $momento,
        array $revisionesNormalizadas,
        User $usuario
    ): string {
        if (! in_array($momento, [SolicitudTraspasoRevisionProducto::MOMENTO_ORIGEN, SolicitudTraspasoRevisionProducto::MOMENTO_CEDIS], true)) {
            throw new \InvalidArgumentException('Momento de revisión no válido.');
        }

        $existentes = SolicitudTraspasoRevisionProducto::query()
            ->where('solicitud_traspaso_id', $solicitud->id)
            ->where('momento', $momento)
            ->get();

        foreach ($existentes as $rev) {
            foreach ($rev->evidencia_paths ?? [] as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        SolicitudTraspasoRevisionProducto::query()
            ->where('solicitud_traspaso_id', $solicitud->id)
            ->where('momento', $momento)
            ->delete();

        $estados = [];
        $orden = 0;
        foreach ($revisionesNormalizadas as $rev) {
            $paths = $rev['evidencia_paths_remotas'] ?? [];
            foreach ($rev['evidencias'] ?? [] as $foto) {
                if ($foto instanceof UploadedFile) {
                    $paths[] = $foto->store(
                        "traspasos/revisiones/{$solicitud->id}/{$momento}",
                        'public'
                    );
                }
            }

            SolicitudTraspasoRevisionProducto::create([
                'solicitud_traspaso_id' => $solicitud->id,
                'momento' => $momento,
                'solicitud_traspaso_producto_id' => $rev['solicitud_traspaso_producto_id'],
                'producto_id' => $rev['producto_id'],
                'sku' => $rev['sku'],
                'orden' => $orden++,
                'descripcion_producto' => $rev['descripcion_producto'],
                'estado_fisico' => $rev['estado_fisico'],
                'comentario' => $rev['comentario'],
                'unica_pieza' => $rev['unica_pieza'],
                'mejor_ejemplar' => $rev['mejor_ejemplar'],
                'evidencia_paths' => $paths !== [] ? array_values($paths) : null,
                'registrado_por_id' => $usuario->id,
            ]);

            $estados[] = $rev['estado_fisico'];
        }

        return RevisionFisicaProducto::derivarEstadoGeneral($estados);
    }
}
