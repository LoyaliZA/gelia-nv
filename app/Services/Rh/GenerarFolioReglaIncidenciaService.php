<?php

namespace App\Services\Rh;

use App\Models\CatalogoReglaIncidencia;

class GenerarFolioReglaIncidenciaService
{
    public function ejecutar(): string
    {
        $prefijo = 'REG';
        $separador = '-';
        $padding = 6;

        $patronBusqueda = $prefijo . $separador . '%';

        $ultimo = CatalogoReglaIncidencia::where('folio', 'like', $patronBusqueda)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('folio');

        $numero = 1;
        if ($ultimo) {
            $partes = explode($separador, $ultimo);
            $ultimoNumero = end($partes);
            if (is_numeric($ultimoNumero)) {
                $numero = (int) $ultimoNumero + 1;
            }
        }

        return $prefijo . $separador . str_pad((string) $numero, $padding, '0', STR_PAD_LEFT);
    }
}
