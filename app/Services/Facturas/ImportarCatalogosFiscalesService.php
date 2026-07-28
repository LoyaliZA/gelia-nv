<?php

namespace App\Services\Facturas;

use App\Models\CatalogoRegimenFiscal;
use App\Models\CatalogoUsoCfdi;
use Illuminate\Support\Facades\DB;

class ImportarCatalogosFiscalesService
{
    /**
     * @return array{regimen: int, uso_cfdi: int}
     */
    public function ejecutar(?string $rutaRegimen = null, ?string $rutaUso = null): array
    {
        $rutaRegimen ??= base_path('Implementaciones/datos_importar/REGIMEN_FISCAL.csv');
        $rutaUso ??= base_path('Implementaciones/datos_importar/USO_CFDI.csv');

        return DB::transaction(function () use ($rutaRegimen, $rutaUso) {
            $regimen = $this->importarTabla(
                $rutaRegimen,
                CatalogoRegimenFiscal::class,
                ['código sat', 'codigo sat', 'codigo', 'clave']
            );
            $uso = $this->importarTabla(
                $rutaUso,
                CatalogoUsoCfdi::class,
                ['clave', 'código', 'codigo', 'código sat', 'codigo sat']
            );

            return ['regimen' => $regimen, 'uso_cfdi' => $uso];
        });
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  list<string>  $clavesCodigo
     */
    private function importarTabla(string $ruta, string $modelClass, array $clavesCodigo): int
    {
        if (! is_readable($ruta)) {
            throw new \InvalidArgumentException("No se puede leer el CSV: {$ruta}");
        }

        $handle = fopen($ruta, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException("No se pudo abrir el CSV: {$ruta}");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === null) {
                throw new \InvalidArgumentException("CSV vacío: {$ruta}");
            }

            $headerNorm = array_map(fn ($h) => $this->normalizarEncabezado((string) $h), $header);
            $idxCodigo = $this->indiceColumna($headerNorm, $clavesCodigo);
            $idxNombre = $this->indiceColumna($headerNorm, [
                'régimen fiscal',
                'regimen fiscal',
                'uso del cfdi',
                'uso de cfdi',
                'nombre',
                'descripcion',
                'descripción',
            ]);

            if ($idxCodigo === null || $idxNombre === null) {
                throw new \InvalidArgumentException("Encabezados inválidos en: {$ruta}");
            }

            $count = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if ($this->filaVacia($row)) {
                    continue;
                }

                $codigo = trim((string) ($row[$idxCodigo] ?? ''));
                $nombre = trim((string) ($row[$idxNombre] ?? ''));
                if ($codigo === '' || $nombre === '') {
                    continue;
                }

                $modelClass::query()->updateOrCreate(
                    ['codigo' => $codigo],
                    ['nombre' => $nombre, 'activo' => true]
                );
                $count++;
            }

            return $count;
        } finally {
            fclose($handle);
        }
    }

    private function normalizarEncabezado(string $valor): string
    {
        $valor = preg_replace('/^\xEF\xBB\xBF/', '', $valor) ?? $valor;
        $valor = mb_strtolower(trim($valor));

        return preg_replace('/\s+/', ' ', $valor) ?? $valor;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $candidatos
     */
    private function indiceColumna(array $header, array $candidatos): ?int
    {
        foreach ($candidatos as $candidato) {
            $idx = array_search($candidato, $header, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }

        return null;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function filaVacia(array $row): bool
    {
        foreach ($row as $celda) {
            if (trim((string) $celda) !== '') {
                return false;
            }
        }

        return true;
    }
}
