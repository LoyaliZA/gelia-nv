<?php

namespace App\Support;

use App\Models\Departamento;
use Illuminate\Support\Str;

class RhReciboAssets
{
    /**
     * Un solo logo claro (negro) según departamento — nunca blanco en papel.
     *
     * @return array{mostrar_aromas: bool, mostrar_bellaroma: bool, logos: array<int, array{key: string, base64: string, w: int, h: int, alt: string}>}
     */
    public static function encabezadoParaDepartamento(
        ?string $departamentoNombre = null,
        string $variante = 'negro',
        ?Departamento $departamento = null,
        ?string $logoKey = null,
    ): array {
        // Print/PDF: siempre versión clara; ignorar variante blanco.
        $key = $logoKey
            ?? $departamento?->logo_key_claro
            ?? self::logoKeyPorNombre($departamentoNombre);

        return DepartamentoLogoAssets::encabezadoRecibo($key);
    }

    private static function logoKeyPorNombre(?string $departamentoNombre): ?string
    {
        $nombre = trim((string) $departamentoNombre);
        if ($nombre === '') {
            return null;
        }

        $depto = Departamento::query()
            ->where('nombre', $nombre)
            ->first();

        if ($depto?->logo_key_claro) {
            return $depto->logo_key_claro;
        }

        $lower = Str::lower($nombre);
        if ($lower === 'bellaroma' || str_contains($lower, 'bellaroma')) {
            return 'bellaroma_logo_negro';
        }

        return DepartamentoLogoAssets::FALLBACK_KEY;
    }
}
