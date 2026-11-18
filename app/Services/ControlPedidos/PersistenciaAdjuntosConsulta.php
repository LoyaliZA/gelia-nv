<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Guarda archivos de un ajuste. No sustituye el PDF de Wizerp ni otros documentos de origen. */
final class PersistenciaAdjuntosConsulta
{
    /** @var list<string> */
    private const ORIGENES = [
        PedidoBmaDocumento::TIPO_PDF_PEDIDO,
        PedidoBmaDocumento::TIPO_REMISION,
        PedidoBmaDocumento::TIPO_GUIA,
        PedidoBmaDocumento::TIPO_COTIZACION,
    ];

    public function __construct(private AdjuntosConsultaTienda $adjuntos) {}

    /**
     * @param  list<UploadedFile>  $archivos
     * @param  array{carpeta: string, tipo: string, comentario: string, relacion_tipo?: ?string, relacion_id?: ?int, orden?: int, grupo?: ?string}  $opciones
     * @return list<array<string, mixed>>
     */
    public function guardar(PedidoBma $pedido, array $archivos, array $opciones): array
    {
        $tipo = (string) $opciones['tipo'];
        if (in_array($tipo, self::ORIGENES, true)) {
            throw new \InvalidArgumentException('El ajuste de consulta no sustituye el documento original.');
        }

        $guardados = [];
        $rutas = [];
        $orden = array_key_exists('orden', $opciones)
            ? (int) $opciones['orden']
            : (int) $pedido->documentos()->max('orden');
        $carpeta = trim((string) $opciones['carpeta'], '/');
        try {
            foreach ($archivos as $archivo) {
                if (! $archivo instanceof UploadedFile) {
                    continue;
                }
                $orden++;
                $ruta = $archivo->store('pedidos_bma/'.$pedido->id.'/'.$carpeta, 'public');
                if (! is_string($ruta) || $ruta === '' || ! Storage::disk('public')->exists($ruta)) {
                    throw new \RuntimeException('No se pudo guardar el archivo de la consulta.');
                }
                $rutas[] = $ruta;
                $documento = $pedido->documentos()->create([
                    'tipo' => $tipo,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $archivo->getMimeType(),
                    'tamano_bytes' => $archivo->getSize(),
                    'orden' => $orden,
                    'comentario' => (string) $opciones['comentario'],
                    'relacion_tipo' => $opciones['relacion_tipo'] ?? null,
                    'relacion_id' => $opciones['relacion_id'] ?? null,
                    'activo' => true,
                ]);
                $fila = [
                    'id' => $documento->id,
                    'ruta' => $ruta,
                    'nombre' => $documento->nombre_original,
                    'mime_type' => $documento->mime_type,
                    'tamano_bytes' => (int) $documento->tamano_bytes,
                    'huella' => $this->adjuntos->huella($archivo),
                ];
                if (! empty($opciones['grupo'])) {
                    $fila['grupo'] = $opciones['grupo'];
                }
                $guardados[] = $fila;
            }
        } catch (\Throwable $e) {
            $this->limpiar($rutas);
            throw $e;
        }

        return $guardados;
    }

    /** @param  list<string>  $rutas */
    public function limpiar(array $rutas): void
    {
        if ($rutas === []) {
            return;
        }
        Storage::disk('public')->delete($rutas);
    }
}
