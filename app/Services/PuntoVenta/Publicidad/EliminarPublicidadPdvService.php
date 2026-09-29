<?php

namespace App\Services\PuntoVenta\Publicidad;

use App\Models\Medios\Medio;
use App\Models\PuntoVenta\PdvPantallaPublicidad;
use Illuminate\Support\Facades\Storage;

class EliminarPublicidadPdvService
{
    /**
     * Borra el registro y el archivo local solo cuando ya no queda otra pieza con el mismo medio.
     *
     * @return array{publicidad_id: int, sucursal_id: int|null, archivo_eliminado: bool, archivo_ausente: bool}
     */
    public function eliminarRegistro(PdvPantallaPublicidad $item): array
    {
        $medioId = $item->medio_id;
        $sucursalId = $item->sucursal_id;
        $publicidadId = (int) $item->id;
        $archivoEliminado = false;
        $archivoAusente = false;

        $item->delete();

        if ($medioId) {
            $referencias = PdvPantallaPublicidad::query()->where('medio_id', $medioId)->count();
            if ($referencias === 0) {
                $medio = Medio::query()->find($medioId);
                if ($medio instanceof Medio) {
                    if (filled($medio->ruta_local)) {
                        $disco = Storage::disk(PdvPantallaPublicidad::DISK);
                        if ($disco->exists($medio->ruta_local)) {
                            $disco->delete($medio->ruta_local);
                            $archivoEliminado = true;
                        } else {
                            $archivoAusente = true;
                        }
                    }
                    $medio->update(['estado' => Medio::ESTADO_DELETED]);
                }
            }
        }

        return [
            'publicidad_id' => $publicidadId,
            'sucursal_id' => $sucursalId === null ? null : (int) $sucursalId,
            'archivo_eliminado' => $archivoEliminado,
            'archivo_ausente' => $archivoAusente,
        ];
    }
}
