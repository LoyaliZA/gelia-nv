<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\DocumentoVenta;
use App\Services\Escalonamiento\Excepciones\VinculoDevolucionException;

class ListarCandidatasRemisionVinculada
{
    public function __construct(private CapacidadRemisionVinculada $capacidad) {}

    /**
     * Sugiere remisiones. No elige ni registra un vínculo.
     *
     * @return list<array<string, mixed>>
     */
    public function listar(DocumentoVenta $devolucion): array
    {
        if ($devolucion->tipo !== 'devolucion') {
            throw new VinculoDevolucionException('El documento no es una devolución.');
        }

        $importe = bcadd((string) $devolucion->total, '0', 2);
        $folioOriginal = trim((string) $devolucion->remision_original);
        $fechaDevolucion = $devolucion->fecha_emision?->format('Y-m-d');

        $filas = [];
        $remisiones = DocumentoVenta::query()
            ->with('movimiento')
            ->where('escalonamiento_periodo_id', $devolucion->escalonamiento_periodo_id)
            ->where('cliente_id', $devolucion->cliente_id)
            ->where('moneda', $devolucion->moneda)
            ->where('tipo', 'remision')
            ->where('estado', 'activo')
            ->orderBy('fecha_emision')
            ->orderBy('id')
            ->get();

        foreach ($remisiones as $remision) {
            if ($folioOriginal !== '' && strcasecmp($folioOriginal, (string) $remision->folio) === 0) {
                continue;
            }

            $efecto = $remision->movimiento ? bcadd((string) $remision->movimiento->efecto, '0', 2) : '0.00';
            if (bccomp($efecto, '0.00', 2) <= 0) {
                continue;
            }

            $suma = $this->capacidad->sumaActiva($remision->id);
            $total = bcadd((string) $remision->total, '0', 2);
            $fecha = $remision->fecha_emision?->format('Y-m-d');
            $fuente = is_array($remision->datos_fuente) ? $remision->datos_fuente : [];

            $filas[] = [
                'id' => $remision->id,
                'folio' => $remision->folio,
                'serie' => $remision->serie,
                'sucursal' => $remision->sucursal,
                'fecha_emision' => $fecha,
                'fecha_hora' => $fuente['fecha_hora'] ?? null,
                'total' => $total,
                'suma_devoluciones_activas' => $suma,
                'mismo_dia' => $fecha !== null && $fecha === $fechaDevolucion,
                'puede_recibir' => $this->capacidad->acepta($total, $suma, $importe),
            ];
        }

        usort($filas, function (array $a, array $b) use ($fechaDevolucion): int {
            $distanciaA = $this->distancia($a['fecha_emision'], $fechaDevolucion);
            $distanciaB = $this->distancia($b['fecha_emision'], $fechaDevolucion);
            if ($distanciaA !== $distanciaB) {
                return $distanciaA <=> $distanciaB;
            }

            return strcmp((string) ($a['fecha_hora'] ?? $a['fecha_emision']), (string) ($b['fecha_hora'] ?? $b['fecha_emision']));
        });

        return $filas;
    }

    private function distancia(?string $fecha, ?string $origen): int
    {
        if ($fecha === null || $origen === null) {
            return PHP_INT_MAX;
        }

        $desde = strtotime($origen.' UTC');
        $hasta = strtotime($fecha.' UTC');
        if ($desde === false || $hasta === false) {
            return PHP_INT_MAX;
        }

        return abs((int) (($hasta - $desde) / 86400));
    }
}
