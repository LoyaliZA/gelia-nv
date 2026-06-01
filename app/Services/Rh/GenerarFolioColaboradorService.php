<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhConfiguracion;

class GenerarFolioColaboradorService
{
    public function ejecutar(?RhConfiguracion $config = null): string
    {
        $config = $config ?? RhConfiguracion::obtener();

        $prefijo = strtoupper(trim($config->folio_prefijo ?: 'COL'));
        $separador = $config->folio_separador ?? '-';
        $padding = max(1, min(12, (int) $config->folio_padding));

        $base = $prefijo;
        if ($config->folio_incluir_anio) {
            $base .= $separador . now()->format('Y');
        }

        $patronBusqueda = $base . $separador . '%';

        $ultimo = RhColaborador::withTrashed()
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

    public function preview(?RhConfiguracion $config = null): string
    {
        $config = $config ?? RhConfiguracion::obtener();

        $prefijo = strtoupper(trim($config->folio_prefijo ?: 'COL'));
        $separador = $config->folio_separador ?? '-';
        $padding = max(1, min(12, (int) $config->folio_padding));

        $base = $prefijo;
        if ($config->folio_incluir_anio) {
            $base .= $separador . now()->format('Y');
        }

        $patronBusqueda = $base . $separador . '%';

        $ultimo = RhColaborador::withTrashed()
            ->where('folio', 'like', $patronBusqueda)
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
