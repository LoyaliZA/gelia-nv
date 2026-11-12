<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** El cupo de 50 MB es de la consulta abierta, no de cada archivo ni de otras consultas del pedido. */
final class AdjuntosConsultaTienda
{
    public const CLAVE = 'ajuste_tienda_adjuntos_max_bytes';

    public function maxBytes(): int
    {
        return max(1, (int) config('control_pedidos.'.self::CLAVE, 52_428_800));
    }

    public function ciclo(PedidoBma $pedido, bool $nuevo): string
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $revision = is_array($json['revision_disponibilidad'] ?? null) ? $json['revision_disponibilidad'] : [];
        $actual = $revision['ciclo_id'] ?? null;
        if (! $nuevo && is_string($actual) && $actual !== '') {
            return $actual;
        }
        $ciclo = (string) Str::uuid();
        $revision['ciclo_id'] = $ciclo;
        $json['revision_disponibilidad'] = $revision;
        $pedido->solicitud_preparacion_json = $json;

        return $ciclo;
    }

    public function bytesUsados(PedidoBma $pedido, string $ciclo): int
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $ids = [];
        foreach (['anexo_sin_remision', 'retiro_piezas'] as $clave) {
            foreach ((array) ($json[$clave] ?? []) as $entrada) {
                if (! is_array($entrada) || ($entrada['ciclo_id'] ?? null) !== $ciclo) {
                    continue;
                }
                foreach (['documentos', 'documentos_piezas', 'documentos_peticion'] as $lista) {
                    foreach ((array) ($entrada[$lista] ?? []) as $doc) {
                        if (is_array($doc) && ! empty($doc['id'])) {
                            $ids[] = (int) $doc['id'];
                        }
                    }
                }
                if (! empty($entrada['documento_id'])) {
                    $ids[] = (int) $entrada['documento_id'];
                }
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return 0;
        }

        return (int) $pedido->documentos()->whereIn('id', $ids)->sum('tamano_bytes');
    }

    /**
     * @param  list<UploadedFile>  $archivos
     */
    public function asegurar(int $usados, array $archivos): void
    {
        $nuevos = 0;
        foreach ($archivos as $archivo) {
            $nuevos += max(0, (int) $archivo->getSize());
        }
        if ($usados + $nuevos > $this->maxBytes()) {
            throw ValidationException::withMessages([
                'archivos' => 'Los archivos de esta consulta superan 50 MB en total. Quite alguno antes de enviar. Lo ya guardado se conserva.',
            ]);
        }
    }

    public function huella(UploadedFile $archivo): string
    {
        $ruta = $archivo->getRealPath();

        return is_string($ruta) && $ruta !== '' && is_file($ruta)
            ? (string) hash_file('sha1', $ruta)
            : hash('sha1', (string) $archivo->get());
    }

    /**
     * @param  list<string>  $conocidas
     * @param  list<mixed>  $archivos
     * @return list<UploadedFile>
     */
    public function sinRepetir(array $conocidas, array $archivos): array
    {
        $salida = [];
        $vistas = array_values(array_filter($conocidas, 'is_string'));
        foreach ($archivos as $archivo) {
            if (! $archivo instanceof UploadedFile) {
                continue;
            }
            $huella = $this->huella($archivo);
            if (in_array($huella, $vistas, true)) {
                continue;
            }
            $vistas[] = $huella;
            $salida[] = $archivo;
        }

        return $salida;
    }
}
