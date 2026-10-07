<?php

namespace App\Services\Escalonamiento;

use App\Models\CatalogoListaDescuento;

class ConstruirSnapshotListasEscalonamiento
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function desdeCatalogo(): array
    {
        return CatalogoListaDescuento::query()
            ->orderBy('monto_requerido')
            ->get([
                'id',
                'nombre',
                'monto_requerido',
                'porcentaje_descuento',
                'monto_minimo',
                'monto_maximo',
                'activo',
                'participa_escalonamiento',
            ])
            ->map(fn (CatalogoListaDescuento $lista) => [
                'id' => $lista->id,
                'nombre' => $lista->nombre,
                'monto_requerido' => (string) $lista->monto_requerido,
                'porcentaje_descuento' => $lista->porcentaje_descuento !== null
                    ? (string) $lista->porcentaje_descuento
                    : null,
                'monto_minimo' => $lista->monto_minimo,
                'monto_maximo' => $lista->monto_maximo,
                'activo' => (bool) $lista->activo,
                'participa_escalonamiento' => (bool) $lista->participa_escalonamiento,
            ])
            ->all();
    }
}
