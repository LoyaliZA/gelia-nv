<?php

namespace App\Services\Catalogos;

use App\Models\Almacen;
use App\Models\CatalogoCategoriaProducto;
use App\Models\CatalogoMarcaProducto;
use App\Models\CatalogoTipoAlmacen;
use App\Models\Sucursal;
use App\Services\Almacenes\NormalizarTextoImportacionService;
use App\Services\Almacenes\RegistrarAuditoriaAlmacenService;
use App\Services\Almacenes\ReporteErroresImportacionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportarCatalogoAlmacenService
{
    public function __construct(
        private readonly PlantillaImportacionCatalogoService $plantillas,
        private readonly NormalizarTextoImportacionService $normalizador,
        private readonly ReporteErroresImportacionService $reporte,
        private readonly RegistrarAuditoriaAlmacenService $auditoria,
    ) {}

    public function ejecutar(string $tipo, UploadedFile $archivo): array
    {
        if (! in_array($tipo, $this->plantillas->tiposValidos(), true) || in_array($tipo, ['productos', 'inventarios'], true)) {
            throw ValidationException::withMessages([
                'archivo' => 'Tipo de catálogo no válido para importación simple.',
            ]);
        }

        $path = $archivo->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo subido.',
            ]);
        }

        $stats = ['importados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];

        DB::transaction(function () use ($tipo, $path, &$stats) {
            $rows = (new FastExcel)->import($path);

            foreach ($rows as $index => $row) {
                $fila = $this->normalizarFila($row);
                if ($this->filaVacia($fila)) {
                    continue;
                }

                $numeroFila = $index + 2;
                $referencia = $fila['codigo'] ?? $fila['nombre'] ?? '—';

                try {
                    match ($tipo) {
                        'sucursales' => $this->importarSucursal($fila, $stats),
                        'tipos_almacen' => $this->importarTipoAlmacen($fila, $stats),
                        'marcas_producto' => $this->importarMarca($fila, $stats),
                        'categorias_producto' => $this->importarCategoria($fila, $stats),
                        'almacenes' => $this->importarAlmacen($fila, $stats),
                        default => throw new \InvalidArgumentException('Tipo no soportado'),
                    };
                } catch (\Throwable $e) {
                    $stats['omitidos']++;
                    $mensaje = $e->getMessage();
                    $stats['errores'][] = "Fila {$numeroFila}: {$mensaje}";
                    $this->reporte->agregar($numeroFila, (string) $referencia, 'general', $mensaje);
                }
            }
        });

        $token = $this->reporte->generarCsv();
        $stats['reporte_url'] = $token
            ? route('almacenes.importaciones.reporte_errores', ['token' => $token])
            : null;
        $stats['errores_detalle'] = $this->reporte->resumen();

        $this->auditoria->catalogoImportado($tipo, $stats);

        return $stats;
    }

    private function importarSucursal(array $fila, array &$stats): void
    {
        $codigo = trim((string) ($fila['codigo'] ?? ''));
        $nombre = $this->normalizador->texto($fila['nombre'] ?? '');

        if ($codigo === '' || $nombre === null) {
            throw new \RuntimeException('codigo y nombre son obligatorios.');
        }

        $activo = $this->parseActivo($fila['activo'] ?? true);

        $sucursal = Sucursal::where('codigo', $codigo)->first();
        if ($sucursal) {
            $sucursal->update(['nombre' => $nombre, 'activo' => $activo]);
            $stats['actualizados']++;
        } else {
            Sucursal::create(['codigo' => $codigo, 'nombre' => $nombre, 'activo' => $activo]);
            $stats['importados']++;
        }
    }

    private function importarTipoAlmacen(array $fila, array &$stats): void
    {
        $nombre = $this->normalizador->texto($fila['nombre'] ?? '');
        if ($nombre === null) {
            throw new \RuntimeException('nombre es obligatorio.');
        }

        $tipo = CatalogoTipoAlmacen::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();
        if ($tipo) {
            $tipo->update(['nombre' => $nombre]);
            $stats['actualizados']++;
        } else {
            CatalogoTipoAlmacen::create(['nombre' => $nombre]);
            $stats['importados']++;
        }
    }

    private function importarMarca(array $fila, array &$stats): void
    {
        $nombre = $this->normalizador->texto($fila['nombre'] ?? '');
        if ($nombre === null) {
            throw new \RuntimeException('nombre es obligatorio.');
        }

        $activo = $this->parseActivo($fila['activo'] ?? true);
        $marca = CatalogoMarcaProducto::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();

        if ($marca) {
            $marca->update(['nombre' => $nombre, 'activo' => $activo]);
            $stats['actualizados']++;
        } else {
            CatalogoMarcaProducto::create(['nombre' => $nombre, 'activo' => $activo]);
            $stats['importados']++;
        }
    }

    private function importarCategoria(array $fila, array &$stats): void
    {
        $nombre = $this->normalizador->texto($fila['nombre'] ?? '');
        if ($nombre === null) {
            throw new \RuntimeException('nombre es obligatorio.');
        }

        $categoria = CatalogoCategoriaProducto::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();
        if ($categoria) {
            $categoria->update(['nombre' => $nombre]);
            $stats['actualizados']++;
        } else {
            CatalogoCategoriaProducto::create(['nombre' => $nombre]);
            $stats['importados']++;
        }
    }

    private function importarAlmacen(array $fila, array &$stats): void
    {
        $codigo = trim((string) ($fila['codigo'] ?? ''));
        $nombre = $this->normalizador->texto($fila['nombre'] ?? '');

        if ($codigo === '' || $nombre === null) {
            throw new \RuntimeException('codigo y nombre son obligatorios.');
        }

        $sucursalId = null;
        $sucursalCodigo = trim((string) ($fila['sucursal_codigo'] ?? ''));
        if ($sucursalCodigo !== '') {
            $sucursal = Sucursal::where('codigo', $sucursalCodigo)->first();
            if (! $sucursal) {
                throw new \RuntimeException("sucursal_codigo «{$sucursalCodigo}» no existe.");
            }
            $sucursalId = $sucursal->id;
        }

        $tipoAlmacenId = null;
        $tipoNombre = $this->normalizador->texto($fila['tipo_almacen'] ?? '');
        if ($tipoNombre !== null) {
            $tipo = CatalogoTipoAlmacen::whereRaw('UPPER(nombre) = ?', [$tipoNombre])->first();
            if (! $tipo) {
                throw new \RuntimeException("tipo_almacen «{$tipoNombre}» no existe.");
            }
            $tipoAlmacenId = $tipo->id;
        }

        $activo = $this->parseActivo($fila['activo'] ?? true);
        $almacen = Almacen::where('codigo', $codigo)->first();

        $payload = [
            'nombre' => $nombre,
            'sucursal_id' => $sucursalId,
            'tipo_almacen_id' => $tipoAlmacenId,
            'activo' => $activo,
        ];

        if ($almacen) {
            $almacen->update($payload);
            $stats['actualizados']++;
        } else {
            Almacen::create(array_merge(['codigo' => $codigo], $payload));
            $stats['importados']++;
        }
    }

    private function normalizarFila(array $row): array
    {
        $normalizada = [];
        foreach ($row as $key => $value) {
            $normalizada[mb_strtolower(trim((string) $key))] = is_string($value) ? trim($value) : $value;
        }

        return $normalizada;
    }

    private function filaVacia(array $fila): bool
    {
        return empty(array_filter($fila, fn ($v) => $v !== null && $v !== ''));
    }

    private function parseActivo(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        $str = mb_strtolower(trim((string) $valor));

        if ($str === '') {
            return true;
        }

        return ! in_array($str, ['0', 'no', 'false', 'inactivo'], true);
    }
}
