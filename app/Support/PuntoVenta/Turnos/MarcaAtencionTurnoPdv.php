<?php

namespace App\Support\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdvAtencion;

/**
 * Marca visible de la atención actual. El folio persistido no cambia.
 */
final class MarcaAtencionTurnoPdv
{
    public const NORMAL = 'normal';

    public const REATENCION = 'reatencion';

    public const TRANSFERENCIA = 'transferencia';

    /**
     * @param  iterable<int, TurnoPdvAtencion>  $atenciones
     * @return array<int, array{marca_atencion: string, folio_visible: string, numero_reatencion: int|null}>
     */
    public static function paraAtenciones(iterable $atenciones): array
    {
        $turnoIds = [];
        foreach ($atenciones as $atencion) {
            if ($atencion instanceof TurnoPdvAtencion) {
                $turnoIds[] = (int) $atencion->turno_id;
            }
        }

        $conteos = self::conteosReatencion($turnoIds);
        $marcas = [];

        foreach ($atenciones as $atencion) {
            if (! $atencion instanceof TurnoPdvAtencion) {
                continue;
            }

            $numero = 0;
            if (! $atencion->es_transferencia && (int) $atencion->numero_secuencia > 1) {
                $numero = (int) ($conteos[(int) $atencion->turno_id] ?? 0);
            }

            $folio = (string) ($atencion->turno?->folio ?? '');
            $marcas[(int) $atencion->id] = self::desde($folio, (bool) $atencion->es_transferencia, $numero);
        }

        return $marcas;
    }

    /**
     * @return array{marca_atencion: string, folio_visible: string, numero_reatencion: int|null}
     */
    public static function desde(string $folio, bool $esTransferencia, int $numeroReatencion): array
    {
        if ($esTransferencia) {
            return [
                'marca_atencion' => self::TRANSFERENCIA,
                'folio_visible' => self::conPrefijo($folio, 'T'),
                'numero_reatencion' => null,
            ];
        }

        if ($numeroReatencion > 0) {
            return [
                'marca_atencion' => self::REATENCION,
                'folio_visible' => self::conPrefijo($folio, 'R'),
                'numero_reatencion' => $numeroReatencion,
            ];
        }

        return [
            'marca_atencion' => self::NORMAL,
            'folio_visible' => $folio,
            'numero_reatencion' => null,
        ];
    }

    /**
     * @param  list<int>  $turnoIds
     * @return array<int, int>
     */
    private static function conteosReatencion(array $turnoIds): array
    {
        $turnoIds = array_values(array_unique(array_filter($turnoIds)));
        if ($turnoIds === []) {
            return [];
        }

        return TurnoPdvAtencion::query()
            ->whereIn('turno_id', $turnoIds)
            ->where('es_transferencia', false)
            ->where('numero_secuencia', '>', 1)
            ->selectRaw('turno_id, COUNT(*) as total')
            ->groupBy('turno_id')
            ->pluck('total', 'turno_id')
            ->map(static fn ($total): int => (int) $total)
            ->all();
    }

    private static function conPrefijo(string $folio, string $prefijo): string
    {
        $partes = explode('-', $folio, 2);
        if (count($partes) === 2 && $partes[1] !== '') {
            return $prefijo.'-'.$partes[1];
        }

        return $folio === '' ? $prefijo : $prefijo.'-'.$folio;
    }
}
