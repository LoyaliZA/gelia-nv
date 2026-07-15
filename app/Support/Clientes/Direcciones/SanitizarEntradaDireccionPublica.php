<?php

namespace App\Support\Clientes\Direcciones;

/**
 * Limpia y valida texto de formularios públicos antes de persistir.
 */
class SanitizarEntradaDireccionPublica
{
    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function ejecutar(array $datos): array
    {
        $salida = [];

        foreach ($datos as $clave => $valor) {
            if (is_bool($valor) || is_int($valor) || is_float($valor) || $valor === null) {
                $salida[$clave] = $valor;
                continue;
            }

            if (! is_string($valor)) {
                continue;
            }

            $salida[$clave] = self::texto($valor, permitirMultilinea: in_array($clave, [
                'referencias',
                'indicaciones_entrega',
                'comentario',
            ], true));
        }

        return $salida;
    }

    public static function texto(string $valor, bool $permitirMultilinea = false): string
    {
        $valor = str_replace("\0", '', $valor);
        $valor = strip_tags($valor);
        $valor = html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $valor = strip_tags($valor);

        $valor = preg_replace('/(?:javascript|vbscript|data)\s*:/iu', '', $valor) ?? $valor;
        $valor = preg_replace('/on[a-z]+\s*=/iu', '', $valor) ?? $valor;
        $valor = preg_replace('/<\s*\/?\s*(script|iframe|object|embed|link|meta|svg)\b[^>]*>/iu', '', $valor) ?? $valor;

        if ($permitirMultilinea) {
            $valor = preg_replace("/[^\P{C}\n\t]/u", '', $valor) ?? $valor;
            $valor = preg_replace("/\r\n?/", "\n", $valor) ?? $valor;
            $valor = preg_replace("/\n{3,}/", "\n\n", $valor) ?? $valor;
        } else {
            $valor = preg_replace('/\p{C}+/u', '', $valor) ?? $valor;
            $valor = preg_replace('/\s+/u', ' ', $valor) ?? $valor;
        }

        return trim($valor);
    }

    public static function esTextoSeguro(string $valor): bool
    {
        if ($valor === '') {
            return true;
        }

        if (preg_match('/<\s*\/?\s*(script|iframe|object|embed)\b/i', $valor)) {
            return false;
        }

        if (preg_match('/(?:javascript|vbscript)\s*:/i', $valor)) {
            return false;
        }

        if (preg_match('/(\b(union|select|insert|update|delete|drop|alter|exec|execute)\b.+\b(from|into|table|database)\b)|(--\s)|(\/\*)|(\*\/)/i', $valor)) {
            return false;
        }

        return true;
    }
}
