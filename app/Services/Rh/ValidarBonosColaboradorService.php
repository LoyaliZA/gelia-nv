<?php

namespace App\Services\Rh;

use App\Models\CatalogoBono;
use App\Models\CatalogoPuesto;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ValidarBonosColaboradorService
{
    public function ejecutar(int $catalogoPuestoId, array $bonos): void
    {
        if (empty($bonos)) {
            return;
        }

        $puesto = CatalogoPuesto::with('bonos')->findOrFail($catalogoPuestoId);
        $bonosElegibles = $puesto->bonos->pluck('id');

        foreach ($bonos as $index => $bonoData) {
            $bonoId = (int) ($bonoData['catalogo_bono_id'] ?? 0);
            if (!$bonosElegibles->contains($bonoId)) {
                $nombre = CatalogoBono::find($bonoId)?->nombre ?? 'Bono';
                throw ValidationException::withMessages([
                    "bonos.{$index}.catalogo_bono_id" => "{$nombre} no aplica al puesto seleccionado.",
                ]);
            }
        }
    }

    public function bonosElegiblesParaPuesto(int $catalogoPuestoId): Collection
    {
        return CatalogoPuesto::with('bonos')->findOrFail($catalogoPuestoId)->bonos;
    }
}
