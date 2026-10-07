<?php

namespace App\Services\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use Illuminate\Validation\ValidationException;

class ListaPublicoGeneralEscalonamiento
{
    public function id(): int
    {
        $configId = config('escalonamiento.lista_publico_general_id');
        if ($configId) {
            return (int) $configId;
        }

        $nombres = array_unique(array_filter(array_merge(
            [(string) config('escalonamiento.lista_publico_general_nombre', 'Público General')],
            config('escalonamiento.lista_publico_general_nombres_alternativos', []),
        )));

        $candidatas = CatalogoListaDescuento::query()
            ->where(function ($q) use ($nombres) {
                foreach ($nombres as $nombre) {
                    $q->orWhereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)]);
                }
            })
            ->pluck('id')
            ->unique();

        if ($candidatas->count() === 1) {
            return (int) $candidatas->first();
        }

        throw ValidationException::withMessages([
            'lista_pg' => 'No se pudo resolver una única lista Público General para inactividad. Configure ESCALONAMIENTO_LISTA_PG_ID.',
        ]);
    }
}
