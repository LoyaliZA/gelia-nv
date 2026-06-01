<?php

namespace App\Services\Rh;

use App\Models\RhConfiguracion;
use App\Models\RhSalidaPersonal;

class GenerarFolioSalidaService
{
    public function ejecutar(?RhConfiguracion $config = null): string
    {
        $config = $config ?? RhConfiguracion::obtener();

        $prefijo = strtoupper(trim($config->sal_folio_prefijo ?: 'SAL'));
        $separador = $config->folio_separador ?? '-';
        $padding = max(1, min(12, (int) $config->sal_folio_padding));

        $base = $prefijo;
        $patronBusqueda = $base . $separador . '%';

        $ultimo = RhSalidaPersonal::withTrashed()
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
