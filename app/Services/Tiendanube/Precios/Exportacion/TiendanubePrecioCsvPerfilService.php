<?php

namespace App\Services\Tiendanube\Precios\Exportacion;

use App\Exceptions\Tiendanube\TiendanubePrecioCsvException;
use App\Models\Tiendanube\TiendanubePrecioCsvPerfil;
use App\Support\Tiendanube\Precios\TiendanubePrecioCsvColumnaCatalogo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SplFileObject;

class TiendanubePrecioCsvPerfilService
{
    public function consultar(int $storeId): array
    {
        $perfil = $this->actual($storeId);

        return [
            'perfil' => $perfil ? $this->serializar($perfil) : null,
            'validado' => $perfil?->estaValidado() ?? false,
            'columnas' => TiendanubePrecioCsvColumnaCatalogo::metadatosUi(),
            'presets' => TiendanubePrecioCsvColumnaCatalogo::presets(),
            'preset_default' => TiendanubePrecioCsvColumnaCatalogo::PRESET_SOLO_PRECIOS,
            'encabezados_canonicos' => TiendanubePrecioCsvColumnaCatalogo::encabezados(),
            'mensaje_sin_perfil' => $perfil?->estaValidado()
                ? null
                : 'Configura una exportación de ejemplo de tu tienda',
        ];
    }

    public function actual(?int $storeId): ?TiendanubePrecioCsvPerfil
    {
        if (! $storeId) {
            return null;
        }

        return TiendanubePrecioCsvPerfil::query()
            ->where('store_id', $storeId)
            ->orderByDesc('version')
            ->first();
    }

    public function exigirValidado(int $storeId): TiendanubePrecioCsvPerfil
    {
        $perfil = $this->actual($storeId);
        if (! $perfil || ! $perfil->estaValidado()) {
            throw new TiendanubePrecioCsvException(
                'Configura una exportación de ejemplo de tu tienda',
                'perfil_faltante'
            );
        }
        if ((int) $perfil->store_id !== $storeId) {
            throw new TiendanubePrecioCsvException(
                'La plantilla CSV pertenece a otra tienda.',
                'tienda_incompatible'
            );
        }

        return $perfil;
    }

    public function validarPlantilla(UploadedFile $archivo, int $storeId, int $userId): TiendanubePrecioCsvPerfil
    {
        $encabezados = $this->leerEncabezados($archivo);
        $canonicos = TiendanubePrecioCsvColumnaCatalogo::encabezados();
        if ($encabezados !== $canonicos) {
            throw new TiendanubePrecioCsvException(
                'Los encabezados no coinciden con la plantilla nativa de TiendaNube. Sube una exportación reciente del panel.',
                'plantilla_invalida',
                422,
                ['esperados' => $canonicos, 'recibidos' => $encabezados]
            );
        }

        $actual = $this->actual($storeId);
        $version = $actual ? ((int) $actual->version + 1) : 1;

        $perfil = TiendanubePrecioCsvPerfil::query()->create([
            'store_id' => $storeId,
            'version' => $version,
            'estado' => TiendanubePrecioCsvPerfil::ESTADO_VALIDADO,
            'encabezados_canonicos' => $canonicos,
            'delimiter' => ',',
            'decimal_sep' => '.',
            'encoding' => 'UTF-8',
            'presets' => TiendanubePrecioCsvColumnaCatalogo::presets(),
            'preset_default' => TiendanubePrecioCsvColumnaCatalogo::PRESET_SOLO_PRECIOS,
            'contract_version' => TiendanubePrecioCsvColumnaCatalogo::CONTRACT_VERSION,
            'validado_por' => $userId,
            'validado_at' => now(),
            'plantilla_fecha' => now(),
        ]);

        $dir = 'tiendanube/csv-perfiles/'.$perfil->id;
        Storage::disk('local')->makeDirectory($dir);
        $path = $archivo->storeAs($dir, 'plantilla.csv', 'local');
        $perfil->update(['plantilla_path' => $path]);

        return $perfil->fresh() ?? $perfil;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializar(TiendanubePrecioCsvPerfil $perfil): array
    {
        return [
            'id' => $perfil->id,
            'store_id' => (int) $perfil->store_id,
            'version' => (int) $perfil->version,
            'estado' => $perfil->estado,
            'encabezados_canonicos' => $perfil->encabezados_canonicos,
            'presets' => $perfil->presets,
            'preset_default' => $perfil->preset_default,
            'contract_version' => $perfil->contract_version,
            'plantilla_fecha' => $perfil->plantilla_fecha?->toIso8601String(),
            'validado_at' => $perfil->validado_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<string>
     */
    private function leerEncabezados(UploadedFile $archivo): array
    {
        $path = $archivo->getRealPath();
        if ($path === false) {
            throw new TiendanubePrecioCsvException('No se pudo leer el archivo de plantilla.', 'archivo_invalido');
        }

        $csv = new SplFileObject($path, 'r');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $csv->setCsvControl(',');
        $fila = $csv->fgetcsv();
        if (! is_array($fila) || $fila === [null] || $fila === false) {
            throw new TiendanubePrecioCsvException('La plantilla no tiene encabezados.', 'plantilla_invalida');
        }

        $salida = [];
        foreach ($fila as $i => $celda) {
            $texto = trim((string) $celda);
            if ($i === 0) {
                $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto) ?? $texto;
            }
            if ($texto !== '') {
                $salida[] = $texto;
            }
        }

        return $salida;
    }
}
