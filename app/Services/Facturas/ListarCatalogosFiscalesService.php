<?php

namespace App\Services\Facturas;

use App\Models\CatalogoRegimenFiscal;
use App\Models\CatalogoUsoCfdi;

class ListarCatalogosFiscalesService
{
    /**
     * @return array{regimen_fiscal: list<array{codigo: string, nombre: string}>, uso_cfdi: list<array{codigo: string, nombre: string}>}
     */
    public function activosParaUi(): array
    {
        return [
            'regimen_fiscal' => CatalogoRegimenFiscal::query()
                ->activos()
                ->orderBy('codigo')
                ->get(['codigo', 'nombre'])
                ->map(fn (CatalogoRegimenFiscal $r) => [
                    'codigo' => $r->codigo,
                    'nombre' => $r->nombre,
                ])
                ->values()
                ->all(),
            'uso_cfdi' => CatalogoUsoCfdi::query()
                ->activos()
                ->orderBy('codigo')
                ->get(['codigo', 'nombre'])
                ->map(fn (CatalogoUsoCfdi $u) => [
                    'codigo' => $u->codigo,
                    'nombre' => $u->nombre,
                ])
                ->values()
                ->all(),
        ];
    }
}
