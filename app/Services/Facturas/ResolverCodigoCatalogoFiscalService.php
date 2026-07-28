<?php

namespace App\Services\Facturas;

use App\Models\CatalogoRegimenFiscal;
use App\Models\CatalogoUsoCfdi;
use Illuminate\Support\Str;

/**
 * Resuelve régimen / uso CFDI desde código SAT o nombre exacto del catálogo.
 */
class ResolverCodigoCatalogoFiscalService
{
    /** @var array<string, string>|null */
    private ?array $regimenPorCodigo = null;

    /** @var array<string, string>|null */
    private ?array $regimenPorNombre = null;

    /** @var array<string, string>|null */
    private ?array $usoPorCodigo = null;

    /** @var array<string, string>|null */
    private ?array $usoPorNombre = null;

    public function regimen(?string $entrada): ?string
    {
        $this->cargar();
        $entrada = trim((string) $entrada);
        if ($entrada === '') {
            return null;
        }

        if (isset($this->regimenPorCodigo[$entrada])) {
            return $entrada;
        }

        $norm = $this->normNombre($entrada);

        return $this->regimenPorNombre[$norm] ?? null;
    }

    public function uso(?string $entrada): ?string
    {
        $this->cargar();
        $entrada = trim((string) $entrada);
        if ($entrada === '') {
            return null;
        }

        $upper = Str::upper($entrada);
        if (isset($this->usoPorCodigo[$upper])) {
            return $upper;
        }

        $norm = $this->normNombre($entrada);

        return $this->usoPorNombre[$norm] ?? null;
    }

    private function cargar(): void
    {
        if ($this->regimenPorCodigo !== null) {
            return;
        }

        $this->regimenPorCodigo = [];
        $this->regimenPorNombre = [];
        foreach (CatalogoRegimenFiscal::query()->where('activo', true)->get(['codigo', 'nombre']) as $row) {
            $this->regimenPorCodigo[$row->codigo] = $row->codigo;
            $this->regimenPorNombre[$this->normNombre($row->nombre)] = $row->codigo;
        }

        $this->usoPorCodigo = [];
        $this->usoPorNombre = [];
        foreach (CatalogoUsoCfdi::query()->where('activo', true)->get(['codigo', 'nombre']) as $row) {
            $codigo = Str::upper($row->codigo);
            $this->usoPorCodigo[$codigo] = $codigo;
            $this->usoPorNombre[$this->normNombre($row->nombre)] = $codigo;
        }
    }

    private function normNombre(string $nombre): string
    {
        return Str::lower(Str::ascii(trim($nombre)));
    }
}
