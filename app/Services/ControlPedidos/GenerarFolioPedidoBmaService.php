<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;

class GenerarFolioPedidoBmaService
{
    public function ejecutar(): string
    {
        $anio = now()->format('Y');
        $prefijo = "PBMA-{$anio}-";

        $ultimo = PedidoBma::withTrashed()
            ->where('folio', 'like', "{$prefijo}%")
            ->where('folio', 'not like', '%-C%')
            ->orderByDesc('id')
            ->value('folio');

        $secuencia = 1;
        if ($ultimo && preg_match('/-(\d+)$/', $ultimo, $matches)) {
            $secuencia = (int) $matches[1] + 1;
        }

        return $prefijo . str_pad((string) $secuencia, 5, '0', STR_PAD_LEFT);
    }

    public function ejecutarComplemento(PedidoBma $principal): string
    {
        $base = preg_replace('/-C\d+$/', '', (string) $principal->folio) ?: (string) $principal->folio;

        $ultimo = PedidoBma::withTrashed()
            ->where('folio', 'like', "{$base}-C%")
            ->orderByDesc('id')
            ->value('folio');

        $n = 1;
        if ($ultimo && preg_match('/-C(\d+)$/', $ultimo, $matches)) {
            $n = (int) $matches[1] + 1;
        }

        return "{$base}-C{$n}";
    }

    /** Extrae el siguiente número Cn a partir de una lista de folios (útil en tests sin DB). */
    public static function siguienteSufijoComplemento(string $folioBase, array $foliosExistentes): string
    {
        $base = preg_replace('/-C\d+$/', '', $folioBase) ?: $folioBase;
        $max = 0;
        foreach ($foliosExistentes as $folio) {
            if (preg_match('/^'.preg_quote($base, '/').'-C(\d+)$/', (string) $folio, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return "{$base}-C".($max + 1);
    }
}
