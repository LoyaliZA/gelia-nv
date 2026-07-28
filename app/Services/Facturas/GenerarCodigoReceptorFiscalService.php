<?php

namespace App\Services\Facturas;

use App\Models\ReceptorFiscal;
use Illuminate\Support\Facades\DB;

class GenerarCodigoReceptorFiscalService
{
    public function siguiente(): string
    {
        return DB::transaction(function () {
            $ultimo = ReceptorFiscal::query()
                ->where('codigo_interno', 'like', 'TF-%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('codigo_interno');

            $n = 0;
            if (is_string($ultimo) && preg_match('/^TF-(\d+)$/', $ultimo, $m)) {
                $n = (int) $m[1];
            }

            return sprintf('TF-%06d', $n + 1);
        });
    }
}
