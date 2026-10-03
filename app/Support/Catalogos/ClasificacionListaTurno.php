<?php

namespace App\Support\Catalogos;

/**
 * Tono visual y prioridad de cola que una lista de precios aporta a un turno.
 * La prioridad vacía deja a Diamante delante del resto; un número mayor se atiende antes.
 */
final class ClasificacionListaTurno
{
    public const TONOS = ['bronce', 'plata', 'oro', 'diamante'];

    public const PRIORIDAD_DIAMANTE_AUTOMATICA = 10;

    /**
     * @return array{tono: ?string, prioridad_cola: int, es_diamante: bool}
     */
    public static function resolver(?string $nombre, ?string $tonoExplicito, int|string|null $prioridadExplicita): array
    {
        $tono = self::tonoValido($tonoExplicito) ?? self::tonoDesdeNombre($nombre);
        $prioridad = $prioridadExplicita === null || $prioridadExplicita === ''
            ? ($tono === 'diamante' ? self::PRIORIDAD_DIAMANTE_AUTOMATICA : 0)
            : max(0, min(99, (int) $prioridadExplicita));

        return [
            'tono' => $tono,
            'prioridad_cola' => $prioridad,
            'es_diamante' => $tono === 'diamante',
        ];
    }

    public static function tonoDesdeNombre(?string $nombre): ?string
    {
        $normalizado = strtoupper(trim((string) $nombre));
        if ($normalizado === '') {
            return null;
        }

        foreach (self::TONOS as $tono) {
            if (preg_match('/\b'.strtoupper($tono).'\b/u', $normalizado) === 1) {
                return $tono;
            }
        }

        return null;
    }

    private static function tonoValido(?string $tono): ?string
    {
        $tono = strtolower(trim((string) $tono));

        return in_array($tono, self::TONOS, true) ? $tono : null;
    }
}
