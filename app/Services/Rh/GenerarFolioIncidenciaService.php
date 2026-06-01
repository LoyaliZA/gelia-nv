<?php

namespace App\Services\Rh;

use App\Models\RhConfiguracion;
use App\Models\RhIncidencia;

class GenerarFolioIncidenciaService
{
    public function ejecutar(?RhConfiguracion $config = null): string
    {
        $config = $config ?? RhConfiguracion::obtener();

        $prefijo = strtoupper(trim($config->inc_folio_prefijo ?: 'INC'));
        $separador = $config->folio_separador ?? '-';
        $padding = max(1, min(12, (int) $config->inc_folio_padding));

        $base = $prefijo;
        $patronBusqueda = $base . $separador . '%';

        $ultimo = RhIncidencia::withTrashed()
            ->where('folio', 'like', $patronBusqueda)
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

        return $base . $separador . str_pad((string) $numero, $padding, '0', STR_PAD_LEFT);
    }
}
