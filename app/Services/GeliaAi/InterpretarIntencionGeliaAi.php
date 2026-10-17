<?php

namespace App\Services\GeliaAi;

/**
 * Rutas deterministas solo para casos inequívocos. Todo lo demás lo resuelve el modelo con tools.
 */
class InterpretarIntencionGeliaAi
{
    public const SALUDO = 'saludo';

    /** Solicitudes / créditos: aún sin capacidad de consulta; solo ayuda del módulo. */
    public const NO_DISPONIBLE = 'solicitudes_creditos';

    /** SKU / código de barras explícito: se precarga la consulta para ahorrar una ronda. */
    public const CODIGO = 'inventario_codigo';

    public const GENERAL = 'general';

    /** @return array{ruta: string, codigo?: string} */
    public function interpretar(string $mensaje, bool $conArchivosNuevos = false): array
    {
        $m = mb_strtolower(trim($mensaje));

        if (! $conArchivosNuevos && preg_match(
            '/^(hola|buen[oa]s(\s+(d[ií]as|tardes|noches))?|hey|qu[eé]\s+tal|gracias|muchas\s+gracias|ok(ay)?|vale|perfecto|listo|adi[oó]s|bye|hasta\s+luego)(\s+gelia)?[\s!¡.,?¿]*$/u',
            $m
        )) {
            return ['ruta' => self::SALUDO];
        }

        if (preg_match('/\b(solicitud(es)?|cr[eé]ditos?|pr[eé]stamos?|adeudos?|cobranza|bur[oó]\s+de\s+cr[eé]dito|financiamientos?|mensualidad(es)?)\b/u', $m)) {
            return ['ruta' => self::NO_DISPONIBLE];
        }

        if (! $conArchivosNuevos
            && ! preg_match('/\b(listados?|importar|importa|archivo|excel|csv)\b/u', $m)
            && ($codigo = $this->extraerCodigo($mensaje)) !== null) {
            return ['ruta' => self::CODIGO, 'codigo' => $codigo];
        }

        return ['ruta' => self::GENERAL];
    }

    public function extraerCodigo(string $mensaje): ?string
    {
        if (preg_match('/(?<![\w-])(\d{8,14})(?![\w-])/u', $mensaje, $mm)) {
            return $mm[1];
        }

        preg_match_all('/(?<![\w-])([A-Za-z0-9][-A-Za-z0-9]{3,31})(?![\w-])/u', $mensaje, $all);
        foreach ($all[1] as $cand) {
            $tieneDigito = (bool) preg_match('/\d/', $cand);
            $tieneLetra = (bool) preg_match('/[A-Za-z]/', $cand);
            $esMedida = (bool) preg_match('/^\d+(ml|mls|g|gr|grs|oz|kg|l|lt|lts|cm|mm|pz|pzs)$/i', $cand);
            if ($tieneDigito && $tieneLetra && ! $esMedida) {
                return strtoupper($cand);
            }
        }

        return null;
    }
}
