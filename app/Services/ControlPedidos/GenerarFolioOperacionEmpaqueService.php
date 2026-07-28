<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\OperacionEmpaque;

class GenerarFolioOperacionEmpaqueService
{
    public function ejecutar(): string
    {
        $dia = now()->format('Ymd');
        $prefijo = "EMP-{$dia}-";

        $ultimo = OperacionEmpaque::query()
            ->where('folio_operacion', 'like', "{$prefijo}%")
            ->orderByDesc('id')
            ->value('folio_operacion');

        $secuencia = 1;
        if ($ultimo && preg_match('/-(\d+)$/', $ultimo, $matches)) {
            $secuencia = (int) $matches[1] + 1;
        }

        return $prefijo . str_pad((string) $secuencia, 4, '0', STR_PAD_LEFT);
    }
}
