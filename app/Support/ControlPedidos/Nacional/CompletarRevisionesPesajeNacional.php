<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use Illuminate\Http\UploadedFile;

final class CompletarRevisionesPesajeNacional
{
    /**
     * Completa las piezas vigentes: Todo OK confirma el conjunto; el detalle
     * solo cubre afectadas y el resto queda en Bueno.
     *
     * @param  list<array<string, mixed>>  $solicitadas
     * @param  list<array<string, mixed>>  $excepciones
     * @param  list<array<string, mixed>>  $revisionesEnviadas
     * @return list<array<string, mixed>>
     */
    public static function completar(array $solicitadas, array $excepciones, bool $confirmacion, array $revisionesEnviadas = []): array
    {
        if ($confirmacion) {
            if ($excepciones !== []) {
                throw new \InvalidArgumentException('La confirmación sin excepciones no admite piezas afectadas.');
            }

            return array_map(fn (array $pieza) => self::revisionDePieza($pieza), $solicitadas);
        }

        if ($excepciones === []) {
            return $revisionesEnviadas;
        }

        $cubetas = [];
        foreach ($solicitadas as $pieza) {
            $cubetas[] = [
                'pieza' => $pieza,
                'faltantes' => 0,
                'detalles' => [],
            ];
        }
        $extras = [];

        foreach ($excepciones as $excepcion) {
            $indice = self::indiceCubeta($cubetas, $excepcion);
            if ($indice === null) {
                $extras[] = $excepcion;
                continue;
            }
            if (($excepcion['condicion'] ?? '') === 'faltante') {
                $cubetas[$indice]['faltantes']++;
            } else {
                $cubetas[$indice]['detalles'][] = $excepcion;
            }
        }

        $resultado = [];
        foreach ($cubetas as $cubeta) {
            $pieza = $cubeta['pieza'];
            $solicitada = (int) ($pieza['cantidad'] ?? 1);
            $faltante = min((int) $cubeta['faltantes'], $solicitada);
            $encontrada = $solicitada - $faltante;
            $detalles = $cubeta['detalles'];

            if ($encontrada === 0) {
                $resultado[] = self::revisionDePieza($pieza, [
                    'cantidad_encontrada' => 0,
                    'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA,
                    'comentario' => 'Sin existencias',
                ]);
                continue;
            }
            if ($detalles !== []) {
                $comentarios = [];
                $evidencias = [];
                $unica = false;
                $mejor = false;
                foreach ($detalles as $detalle) {
                    $texto = trim((string) ($detalle['detalle'] ?? ''));
                    if ($texto !== '') {
                        $comentarios[] = $texto;
                    }
                    foreach ($detalle['evidencias'] ?? [] as $archivo) {
                        if ($archivo instanceof UploadedFile) {
                            $evidencias[] = $archivo;
                        }
                    }
                    $unica = $unica || filter_var($detalle['unica_pieza'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $mejor = $mejor || filter_var($detalle['mejor_ejemplar'] ?? false, FILTER_VALIDATE_BOOLEAN);
                }
                $resultado[] = self::revisionDePieza($pieza, [
                    'cantidad_encontrada' => $encontrada,
                    'estado_fisico' => PedidoBmaRevisionProducto::ESTADO_REGULAR,
                    'comentario' => implode(' · ', $comentarios),
                    'unica_pieza' => $unica,
                    'mejor_ejemplar' => $mejor,
                    'evidencias' => $evidencias,
                ]);
                continue;
            }
            if ($faltante > 0) {
                $resultado[] = self::revisionDePieza($pieza, [
                    'cantidad_encontrada' => $encontrada,
                    'comentario' => "Sin existencias ({$faltante} de {$solicitada})",
                ]);
                continue;
            }
            $resultado[] = self::revisionDePieza($pieza);
        }

        foreach ($extras as $excepcion) {
            $resultado[] = self::revisionDeExcepcion($excepcion);
        }

        return $resultado;
    }

    /**
     * @param  list<array{pieza: array<string, mixed>, faltantes: int, detalles: list<array<string, mixed>>}>  $cubetas
     * @param  array<string, mixed>  $excepcion
     */
    private static function indiceCubeta(array $cubetas, array $excepcion): ?int
    {
        $uuid = trim((string) ($excepcion['client_uuid'] ?? ''));
        if ($uuid !== '') {
            foreach ($cubetas as $indice => $cubeta) {
                if ((string) ($cubeta['pieza']['id'] ?? '') === $uuid) {
                    return $indice;
                }
            }
        }
        $sku = self::skuDe($excepcion['sku'] ?? '');
        if ($sku === '') {
            return null;
        }
        foreach ($cubetas as $indice => $cubeta) {
            $capacidad = (int) ($cubeta['pieza']['cantidad'] ?? 1) - $cubeta['faltantes'] - count($cubeta['detalles']);
            if ($capacidad > 0 && self::skuDe($cubeta['pieza']['sku'] ?? '') === $sku) {
                return $indice;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pieza
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function revisionDePieza(array $pieza, array $extra = []): array
    {
        $solicitada = (int) ($pieza['cantidad'] ?? $pieza['cantidad_solicitada'] ?? 1);

        return [
            'client_uuid' => (string) ($pieza['id'] ?? ''),
            'producto_id' => $pieza['producto_id'] ?? null,
            'sku' => $pieza['sku'] ?? null,
            'descripcion_producto' => (string) ($pieza['descripcion'] ?? $pieza['descripcion_producto'] ?? ''),
            'cantidad_solicitada' => $solicitada,
            'cantidad_encontrada' => (int) ($extra['cantidad_encontrada'] ?? $solicitada),
            'estado_fisico' => $extra['estado_fisico'] ?? PedidoBmaRevisionProducto::ESTADO_BUENO,
            'comentario' => $extra['comentario'] ?? '',
            'unica_pieza' => (bool) ($extra['unica_pieza'] ?? false),
            'mejor_ejemplar' => (bool) ($extra['mejor_ejemplar'] ?? false),
            'evidencias' => $extra['evidencias'] ?? [],
            'pieza_solicitada' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $excepcion
     * @return array<string, mixed>
     */
    private static function revisionDeExcepcion(array $excepcion): array
    {
        $faltante = ($excepcion['condicion'] ?? '') === 'faltante';
        $evidencias = [];
        foreach ($excepcion['evidencias'] ?? [] as $archivo) {
            if ($archivo instanceof UploadedFile) {
                $evidencias[] = $archivo;
            }
        }

        return [
            'client_uuid' => (string) ($excepcion['client_uuid'] ?? $excepcion['clave'] ?? ''),
            'producto_id' => $excepcion['producto_id'] ?? null,
            'sku' => $excepcion['sku'] ?? null,
            'descripcion_producto' => (string) ($excepcion['descripcion'] ?? ''),
            'cantidad_solicitada' => (int) ($excepcion['cantidad'] ?? 1),
            'cantidad_encontrada' => $faltante ? 0 : 1,
            'estado_fisico' => $faltante
                ? PedidoBmaRevisionProducto::ESTADO_SIN_EXISTENCIA
                : PedidoBmaRevisionProducto::ESTADO_REGULAR,
            'comentario' => $faltante ? 'Sin existencias' : (string) ($excepcion['detalle'] ?? ''),
            'unica_pieza' => filter_var($excepcion['unica_pieza'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'mejor_ejemplar' => filter_var($excepcion['mejor_ejemplar'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'evidencias' => $evidencias,
            'pieza_solicitada' => false,
        ];
    }

    private static function skuDe(mixed $valor): string
    {
        return strtolower(trim((string) $valor));
    }
}
