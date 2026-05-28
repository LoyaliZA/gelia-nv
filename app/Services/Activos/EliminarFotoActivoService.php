<?php

namespace App\Services\Activos;

use App\Models\ActivoFoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EliminarFotoActivoService
{
    public function ejecutar(ActivoFoto $foto): void
    {
        DB::transaction(function () use ($foto) {
            $activoId = $foto->activo_id;
            $eraPrincipal = $foto->es_principal;

            Storage::disk('public')->delete($foto->ruta);
            $foto->delete();

            if ($eraPrincipal) {
                $nueva = ActivoFoto::where('activo_id', $activoId)->orderBy('orden')->first();
                if ($nueva) {
                    $nueva->update(['es_principal' => true]);
                }
            }

            $this->renumerarOrden($activoId);
        });
    }

    private function renumerarOrden(int $activoId): void
    {
        ActivoFoto::where('activo_id', $activoId)
            ->orderBy('orden')
            ->get()
            ->each(function (ActivoFoto $foto, int $index) {
                $foto->update(['orden' => $index + 1]);
            });
    }
}
