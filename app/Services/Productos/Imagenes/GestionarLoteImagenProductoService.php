<?php

namespace App\Services\Productos\Imagenes;

use App\Models\Producto;
use App\Models\ProductoImagenLote;
use App\Models\ProductoImagenLoteArchivo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GestionarLoteImagenProductoService
{
    public function __construct(
        private readonly ValidarArchivoImagenProducto $validador,
        private readonly AlmacenArchivoImagenProducto $archivos,
        private readonly GestionarGaleriaProductoService $galeria,
    ) {}

    /**
     * @param  list<UploadedFile>  $archivos
     */
    public function analizar(User $user, array $archivos): ProductoImagenLote
    {
        $this->asegurarCupo($archivos);
        $lote = ProductoImagenLote::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'estado' => 'revision',
            'expires_at' => now()->addHours((int) config('productos_imagenes.intento_horas', 24)),
        ]);

        try {
            $preparados = [];
            foreach (array_values($archivos) as $indice => $archivo) {
                $preparados[] = $this->preparar($lote, $indice, $archivo);
            }
            $this->marcarRepetidos($preparados);
            foreach ($preparados as $fila) {
                $fila->save();
            }
        } catch (\Throwable $e) {
            $this->archivos->borrarTemporalesDe($lote->uuid);
            $lote->delete();
            throw $e;
        }

        return $lote->load('archivos');
    }

    /**
     * @param  list<array<string, mixed>>  $seleccion
     * @return list<array<string, mixed>>
     */
    public function confirmar(ProductoImagenLote $lote, array $seleccion): array
    {
        $lote = $this->vencerSiCorresponde($lote);
        if ($lote->estado === 'expirado') {
            return $lote->archivos->map(fn (ProductoImagenLoteArchivo $archivo) => $this->resultado($archivo))->all();
        }

        $porId = collect($seleccion)->keyBy(fn (array $item) => (int) ($item['id'] ?? 0));
        $resultados = [];
        foreach ($lote->archivos()->orderBy('indice')->get() as $archivo) {
            $orden = $porId->get($archivo->id);
            if (! is_array($orden)) {
                $resultados[] = $this->resultado($archivo);

                continue;
            }
            try {
                $resultados[] = $this->confirmarUno($archivo, $orden);
            } catch (\Throwable $e) {
                $archivo->refresh();
                $mensaje = $e instanceof ValidationException
                    ? (string) collect($e->errors())->flatten()->first()
                    : 'No se pudo asociar el archivo.';
                if ($archivo->estado === 'reclamado' && ! $archivo->imagen_id) {
                    $archivo->estado = $archivo->clasificacion === 'unico' ? 'error' : 'pendiente_revision';
                    $archivo->reclamado_en = null;
                    if ($archivo->clasificacion === 'unico') {
                        $archivo->motivo = $mensaje;
                    }
                    $archivo->save();
                } elseif (! $e instanceof ValidationException && $archivo->estado !== 'completado') {
                    $archivo->estado = 'error';
                    $archivo->motivo = $mensaje;
                    $archivo->reclamado_en = null;
                    $archivo->save();
                }
                $resultados[] = $this->resultado($archivo, $e instanceof ValidationException ? 'error' : null, $mensaje);
            }
        }

        return $resultados;
    }

    public function expirarVencidos(): int
    {
        $n = 0;
        ProductoImagenLote::query()
            ->where('estado', '!=', 'expirado')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (ProductoImagenLote $lote) use (&$n) {
                $this->vencerSiCorresponde($lote);
                $n++;
            });

        return $n;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentar(ProductoImagenLote $lote): array
    {
        $lote = $this->vencerSiCorresponde($lote)->load('archivos');

        return [
            'uuid' => $lote->uuid,
            'estado' => $lote->estado,
            'expires_at' => $lote->expires_at?->toIso8601String(),
            'archivos' => $lote->archivos->map(fn (ProductoImagenLoteArchivo $archivo) => $this->resultado($archivo))->all(),
        ];
    }

    /**
     * @param  list<UploadedFile>  $archivos
     */
    private function asegurarCupo(array $archivos): void
    {
        $maxArchivos = (int) config('productos_imagenes.lote_max_archivos', 20);
        $maxBytes = (int) config('productos_imagenes.lote_max_bytes', 40 * 1024 * 1024);
        $total = 0;
        foreach ($archivos as $archivo) {
            $total += (int) $archivo->getSize();
        }
        if (count($archivos) > $maxArchivos || $total > $maxBytes) {
            throw ValidationException::withMessages([
                'archivos' => 'El intento admite como máximo '.$maxArchivos.' archivos y '.number_format($maxBytes / 1048576, 0).' MB en total.',
            ]);
        }
    }

    private function preparar(ProductoImagenLote $lote, int $indice, UploadedFile $archivo): ProductoImagenLoteArchivo
    {
        $nombre = $this->nombreVisible($archivo->getClientOriginalName());
        $fila = new ProductoImagenLoteArchivo([
            'lote_id' => $lote->id,
            'indice' => $indice,
            'nombre_original' => $nombre,
            'estado' => 'error',
            'clasificacion' => 'invalido',
            'motivo' => null,
        ]);

        $ruta = $archivo->getRealPath();
        if (! is_string($ruta) || ! is_file($ruta)) {
            $fila->motivo = 'No se pudo leer el archivo.';

            return $fila;
        }

        try {
            $meta = $this->validador->inspeccionar($ruta);
        } catch (ValidationException $e) {
            $fila->motivo = (string) collect($e->errors())->flatten()->first();

            return $fila;
        }

        $fila->clave_temporal = $this->archivos->guardarTemporal($lote->uuid, $ruta, $meta['extension']);
        $fila->hash_contenido = hash_file('sha256', $ruta) ?: null;
        $this->proponer($fila, $nombre);

        return $fila;
    }

    private function proponer(ProductoImagenLoteArchivo $fila, string $nombre): void
    {
        preg_match_all('/(?<!\d)(\d{1,9})(?!\d)/', $nombre, $numeros);
        $tokens = array_values(array_unique($numeros[1] ?? []));
        $folios = [];
        foreach ($tokens as $token) {
            $numero = (int) $token;
            if ($numero > 0) {
                $folios[$numero] = $numero;
            }
        }

        $productos = $folios === []
            ? collect()
            : Producto::query()->whereIn('folio', array_values($folios))->get(['id', 'folio', 'sku', 'descripcion']);

        $candidatos = $productos->map(fn (Producto $producto) => [
            'producto_id' => $producto->id,
            'folio' => $producto->folio,
            'sku' => $producto->sku,
            'descripcion' => $producto->descripcion,
        ])->values()->all();

        $fila->candidatos = $candidatos;
        $fila->apoyo_sku = $this->apoyoSku($nombre, $candidatos);
        if (count($candidatos) === 1) {
            $fila->folio_detectado = (int) $candidatos[0]['folio'];
            $fila->producto_id = (int) $candidatos[0]['producto_id'];
            $fila->estado = 'listo';
            $fila->clasificacion = 'unico';
            $fila->motivo = null;

            return;
        }

        $fila->estado = 'pendiente_revision';
        $fila->clasificacion = $candidatos === [] ? 'sin_folio' : 'ambiguo';
        $fila->motivo = $candidatos === []
            ? 'El nombre no trae un folio existente.'
            : 'El nombre coincide con más de un folio. Elija uno o descarte el archivo.';
    }

    /**
     * @param  list<array<string, mixed>>  $candidatos
     */
    private function apoyoSku(string $nombre, array $candidatos): ?string
    {
        preg_match_all('/[A-Za-z0-9][A-Za-z0-9._-]*/', pathinfo($nombre, PATHINFO_FILENAME), $tokens);
        $textos = array_values(array_unique($tokens[0] ?? []));
        if ($textos === []) {
            return null;
        }
        $idsFolio = collect($candidatos)->pluck('producto_id')->map(fn ($id) => (int) $id)->all();
        $sku = Producto::query()->whereIn('sku', $textos)->first(['id', 'sku']);
        if (! $sku instanceof Producto || in_array((int) $sku->id, $idsFolio, true)) {
            return null;
        }

        return 'El nombre contiene el SKU '.$sku->sku.'. Esa coincidencia no asocia el archivo.';
    }

    /**
     * @param  list<ProductoImagenLoteArchivo>  $filas
     */
    private function marcarRepetidos(array $filas): void
    {
        $grupos = [];
        foreach ($filas as $fila) {
            if ($fila->folio_detectado === null || $fila->estado === 'error') {
                continue;
            }
            $grupos[(int) $fila->folio_detectado][] = $fila;
        }
        foreach ($grupos as $grupo) {
            if (count($grupo) < 2) {
                continue;
            }
            foreach ($grupo as $fila) {
                $fila->estado = 'pendiente_revision';
                $fila->clasificacion = 'repetido';
                $fila->motivo = 'El folio se repite en este intento. Confirme cada archivo o descártelo.';
            }
        }
    }

    /**
     * @param  array<string, mixed>  $orden
     * @return array<string, mixed>
     */
    private function confirmarUno(ProductoImagenLoteArchivo $archivo, array $orden): array
    {
        if (($orden['accion'] ?? '') === 'descartar') {
            return $this->descartar($archivo);
        }

        if (($archivo->estado === 'completado' || $archivo->imagen_id) && ($orden['accion'] ?? '') === 'confirmar') {
            return $this->resultado($archivo);
        }
        $productoId = $this->productoConfirmado($archivo, $orden);
        [$reclamado, $reclamoPropio] = $this->reclamar($archivo);
        if (! $reclamoPropio) {
            if ($reclamado->estado === 'completado' || $reclamado->imagen_id) {
                return $this->resultado($reclamado);
            }
            if ($reclamado->estado === 'reclamado') {
                return $this->resultado($reclamado, 'error', 'Otra confirmación está procesando este archivo.');
            }

            return $this->resultado($reclamado);
        }
        if (! $reclamado->clave_temporal) {
            $reclamado->estado = 'error';
            $reclamado->motivo = $reclamado->motivo ?: 'El archivo ya no está disponible para reintentar.';
            $reclamado->reclamado_en = null;
            $reclamado->save();

            return $this->resultado($reclamado);
        }

        $temporal = $reclamado->clave_temporal;
        $imagen = $this->galeria->agregarDesdeTemporal((int) $productoId, $temporal, $reclamado->id);
        $reclamado->imagen_id = $imagen->id;
        $reclamado->producto_id = $imagen->producto_id;
        $reclamado->estado = 'completado';
        $reclamado->motivo = null;
        $reclamado->clave_temporal = null;
        $reclamado->save();
        try {
            $this->archivos->eliminarSiLibre($temporal);
        } catch (\Throwable) {
            $this->archivos->registrarLimpieza($temporal);
        }

        return $this->resultado($reclamado);
    }

    /**
     * @return array{0: ProductoImagenLoteArchivo, 1: bool}
     */
    private function reclamar(ProductoImagenLoteArchivo $archivo): array
    {
        return DB::transaction(function () use ($archivo) {
            $fila = ProductoImagenLoteArchivo::query()->whereKey($archivo->id)->lockForUpdate()->first();
            if (! $fila instanceof ProductoImagenLoteArchivo) {
                throw ValidationException::withMessages([
                    'archivo' => 'El archivo del intento no existe.',
                ]);
            }
            if ($fila->estado === 'completado' || $fila->imagen_id) {
                $fila->estado = 'completado';

                return [$fila, false];
            }
            $vigente = $fila->estado === 'reclamado'
                && $fila->reclamado_en
                && $fila->reclamado_en->gt(now()->subMinutes((int) config('productos_imagenes.reclamo_minutos', 2)));
            if ($vigente) {
                return [$fila, false];
            }
            if (! in_array($fila->estado, ['listo', 'error', 'pendiente_revision', 'reclamado'], true)) {
                return [$fila, false];
            }
            $fila->estado = 'reclamado';
            $fila->reclamado_en = now();
            $fila->save();

            return [$fila, true];
        });
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private function productoConfirmado(ProductoImagenLoteArchivo $fila, array $orden): int
    {
        if (($orden['accion'] ?? '') !== 'confirmar') {
            throw ValidationException::withMessages([
                'accion' => 'La acción del archivo no es válida.',
            ]);
        }
        if ($fila->clasificacion === 'unico' && $fila->producto_id) {
            return (int) $fila->producto_id;
        }

        $explicito = isset($orden['producto_id']) ? (int) $orden['producto_id'] : 0;
        $candidatos = collect($fila->candidatos ?? [])->pluck('producto_id')->map(fn ($id) => (int) $id)->all();
        if ($fila->clasificacion === 'repetido') {
            if (empty($orden['confirmar_repetido']) || ! in_array($explicito, $candidatos, true)) {
                throw ValidationException::withMessages([
                    'archivo' => 'El folio repetido requiere confirmación explícita o descarte.',
                ]);
            }

            return $explicito;
        }
        if ($fila->clasificacion === 'ambiguo' && in_array($explicito, $candidatos, true)) {
            return $explicito;
        }

        throw ValidationException::withMessages([
            'archivo' => $fila->clasificacion === 'sin_folio' || $fila->clasificacion === 'invalido'
                ? 'El folio no existe o el archivo no es válido. Descarte el archivo.'
                : 'La coincidencia sigue pendiente de resolución.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function descartar(ProductoImagenLoteArchivo $archivo): array
    {
        return DB::transaction(function () use ($archivo) {
            $fila = ProductoImagenLoteArchivo::query()->whereKey($archivo->id)->lockForUpdate()->first();
            if (! $fila instanceof ProductoImagenLoteArchivo || $fila->estado === 'completado' || $fila->imagen_id) {
                return $this->resultado($fila ?? $archivo);
            }
            $clave = $fila->clave_temporal;
            $fila->estado = 'descartado';
            $fila->motivo = null;
            $fila->clave_temporal = null;
            $fila->reclamado_en = null;
            $fila->save();
            if (is_string($clave) && $clave !== '') {
                try {
                    $this->archivos->eliminarSiLibre($clave);
                } catch (\Throwable) {
                    $this->archivos->registrarLimpieza($clave);
                }
            }

            return $this->resultado($fila);
        });
    }

    private function vencerSiCorresponde(ProductoImagenLote $lote): ProductoImagenLote
    {
        if ($lote->estado === 'expirado' || $lote->expires_at->isFuture()) {
            return $lote;
        }

        DB::transaction(function () use ($lote) {
            $actual = ProductoImagenLote::query()->whereKey($lote->id)->lockForUpdate()->first();
            if (! $actual instanceof ProductoImagenLote || $actual->estado === 'expirado') {
                return;
            }
            $actual->estado = 'expirado';
            $actual->save();
            $actual->archivos()->where('estado', '!=', 'completado')->update([
                'estado' => 'expirado',
                'clave_temporal' => null,
                'reclamado_en' => null,
            ]);
            $this->archivos->borrarTemporalesDe($actual->uuid);
        });

        return $lote->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function resultado(ProductoImagenLoteArchivo $archivo, ?string $estado = null, ?string $motivo = null): array
    {
        return [
            'id' => $archivo->id,
            'indice' => $archivo->indice,
            'nombre_original' => $archivo->nombre_original,
            'estado' => $estado ?? $archivo->estado,
            'clasificacion' => $archivo->clasificacion,
            'motivo' => $motivo ?? $archivo->motivo,
            'folio_detectado' => $archivo->folio_detectado,
            'producto_id' => $archivo->producto_id,
            'imagen_id' => $archivo->imagen_id,
            'apoyo_sku' => $archivo->apoyo_sku,
            'candidatos' => $archivo->candidatos ?? [],
        ];
    }

    private function nombreVisible(string $nombre): string
    {
        $base = basename(str_replace(["\0", '\\'], '', $nombre));
        $base = preg_replace('/[^A-Za-z0-9._ -]/', '_', $base) ?: 'archivo';

        return substr($base, 0, 180);
    }
}
