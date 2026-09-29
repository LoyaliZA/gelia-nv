<?php

namespace App\Services\Medios;

use App\Contracts\Medios\AlmacenObjetosMedio;
use App\Models\Medios\Medio;
use App\Models\PuntoVenta\PdvPantallaPublicidad;
use Illuminate\Support\Facades\Storage;

class MaterializarMedioLocalService
{
    public function __construct(
        private readonly AlmacenObjetosMedio $almacen,
    ) {}

    public function ejecutar(Medio $medio): void
    {
        if (in_array($medio->estado, [Medio::ESTADO_DELETED, Medio::ESTADO_FAILED], true)) {
            return;
        }
        if (! filled($medio->object_key)) {
            return;
        }

        $relativa = filled($medio->ruta_local) ? (string) $medio->ruta_local : $this->rutaRelativa($medio);
        $disco = Storage::disk(PdvPantallaPublicidad::DISK);
        $disco->makeDirectory(dirname($relativa));
        $absoluta = $disco->path($relativa);
        $esperado = (int) $medio->tamano_bytes;

        if (! $this->archivoCoincide($absoluta, $esperado)) {
            $this->almacen->volcar((string) $medio->object_key, $absoluta);
            if (! $this->archivoCoincide($absoluta, $esperado)) {
                $disco->delete($relativa);
                $medio->update([
                    'ruta_local' => null,
                    'estado' => Medio::ESTADO_FAILED,
                ]);

                return;
            }
        }

        $medio->update([
            'ruta_local' => $relativa,
            'estado' => Medio::ESTADO_READY,
        ]);
        $this->almacen->eliminar((string) $medio->object_key);
    }

    private function rutaRelativa(Medio $medio): string
    {
        $extension = strtolower((string) $medio->extension);
        if ($extension === '') {
            $extension = 'bin';
        }

        return PdvPantallaPublicidad::DIRECTORIO.'/'.$medio->uuid.'.'.$extension;
    }

    private function archivoCoincide(string $absoluta, int $esperado): bool
    {
        return is_file($absoluta) && filesize($absoluta) === $esperado;
    }
}
