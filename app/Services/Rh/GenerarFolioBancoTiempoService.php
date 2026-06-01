<?php

namespace App\Services\Rh;

use App\Models\RhBancoTiempo;
use App\Models\RhConfiguracion;
use Illuminate\Support\Facades\DB;

class GenerarFolioBancoTiempoService
{
    public function ejecutar(?RhConfiguracion $config = null): string
    {
        $config    = $config ?? RhConfiguracion::obtener();
        $prefijo   = strtoupper($config->bdt_folio_prefijo ?? 'BDT');
        $padding   = max(1, (int) ($config->bdt_folio_padding ?? 6));
        $separador = $config->folio_separador ?? '-';

        return DB::transaction(function () use ($prefijo, $padding, $separador) {
            $ultimo = RhBancoTiempo::withTrashed()
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
