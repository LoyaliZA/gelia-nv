<?php

namespace App\Services\Rh;

use App\Models\RhConfiguracion;
use App\Models\RhDeduccion;
use Illuminate\Support\Facades\DB;

class GenerarFolioDeduccionService
{
    public function ejecutar(?RhConfiguracion $config = null): string
    {
        $config = $config ?? RhConfiguracion::obtener();
        $prefijo = strtoupper($config->inc_folio_prefijo ?? 'DED');
        $padding = max(1, (int) ($config->inc_folio_padding ?? 6));
        $separador = $config->folio_separador ?? '-';

        return DB::transaction(function () use ($prefijo, $padding, $separador) {
            $ultimo = RhDeduccion::withTrashed()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('folio');

            $numero = 1;
            if ($ultimo && preg_match('/(\d+)$/', $ultimo, $m)) {
                $numero = ((int) $m[1]) + 1;
            }

            return $prefijo . $separador . str_pad((string) $numero, $padding, '0', STR_PAD_LEFT);
        });
    }
}
