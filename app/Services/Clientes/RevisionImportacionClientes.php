<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\FilaRevisionImportacionCliente;
use App\Models\HistorialMontoCliente;
use App\Models\ImportacionCliente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RevisionImportacionClientes
{
    private const UMBRAL_DIFERENCIA_MONTO = '1000.00';

    /** @var list<array<string, mixed>> */
    private array $loteRevision = [];

    private int $hechasRevision = 0;

    public function __construct(
        private ImportarClientesWizerpService $importador,
        private ProcesarFilaClienteAction $procesador,
        private ValidarArchivoClientesWizerp $validador,
    ) {}

    public function previsualizar(ImportacionCliente $importacion): array
    {
        $path = Storage::disk('local')->path($importacion->ruta_almacenamiento);
        $sha = is_file($path) ? (hash_file('sha256', $path) ?: '') : '';
        try {
            $this->validador->leer($path);
        } catch (ValidationException $e) {
            if (isset($e->errors()['archivo'])) {
                return $this->vistaBloqueada($importacion, $sha, (string) collect($e->errors())->flatten()->first());
            }
            throw $e;
        }

        $analisis = $this->analizar($importacion, true);
        $revision = [
            'archivo_sha256' => $sha,
            'huella_clientes' => $analisis['huella'],
            'numeros' => $analisis['numeros'],
            'token' => Str::random(40),
            'bloqueado' => $analisis['bloqueado'],
            'aplicada' => false,
            'proyeccion' => $analisis['hash'],
            'catalogo' => $this->importador->huellaCatalogoRevision(),
            'estado_clientes' => $analisis['estado_clientes'],
        ];
        $importacion->estado = 'previsualizada';
        $importacion->resumen = [
            'revision' => $revision,
            'conteos' => $analisis['conteos'],
            'bloqueos' => $analisis['bloqueos'],
            'avisos' => $analisis['avisos'],
            'muestras' => $analisis['muestras'],
        ];
        $importacion->save();

        return [
            'importacion_id' => $importacion->id,
            'token' => $revision['token'],
            'bloqueado' => $revision['bloqueado'],
            'bloqueos' => $analisis['bloqueos'],
            'avisos' => $analisis['avisos'],
            'conteos' => $analisis['conteos'],
            'muestras' => $analisis['muestras'],
        ];
    }

    public function assertRevisionVigente(ImportacionCliente $importacion, string $token): ?array
    {
        $importacion->refresh();
        $revision = $importacion->resumen['revision'] ?? null;
        if (! is_array($revision) || ! hash_equals((string) ($revision['token'] ?? ''), $token)) {
            throw ValidationException::withMessages(['archivo' => 'La confirmación no corresponde a la revisión de este archivo.']);
        }
        if (! empty($revision['aplicada']) && is_array($revision['resultado'] ?? null)) {
            return $revision['resultado'];
        }
        if (! empty($revision['bloqueado'])) {
            throw ValidationException::withMessages(['archivo' => 'La revisión detuvo la escritura. Hay que corregir el archivo y revisarlo de nuevo.']);
        }

        $path = Storage::disk('local')->path($importacion->ruta_almacenamiento);
        $analisis = $this->analizar($importacion);
        if (! is_file($path)
            || ! hash_equals((string) $revision['archivo_sha256'], hash_file('sha256', $path) ?: '')
            || ! hash_equals((string) ($revision['proyeccion'] ?? ''), $analisis['hash'])
            || ! hash_equals((string) ($revision['catalogo'] ?? ''), $this->importador->huellaCatalogoRevision())
            || ! hash_equals((string) ($revision['huella_clientes'] ?? ''), $this->huella($revision['numeros'] ?? []))
            || $analisis['bloqueado']) {
            throw ValidationException::withMessages(['archivo' => 'La revisión ya no coincide con los cambios aprobados. Hay que revisar el archivo de nuevo.']);
        }

        return null;
    }

    public function confirmar(ImportacionCliente $importacion, string $token): array
    {
        return $this->importador->duranteBloqueo(function () use ($importacion, $token) {
            $cacheado = $this->assertRevisionVigente($importacion, $token);
            if ($cacheado !== null) {
                return $cacheado;
            }

            $path = Storage::disk('local')->path($importacion->ruta_almacenamiento);
            $archivo = new UploadedFile($path, $importacion->nombre_archivo_original, 'text/csv', null, true);
            $resultado = $this->importador->ejecutar($archivo, $importacion->fresh());
            $importacion->refresh();
            $resumen = $importacion->resumen ?? [];
            $resumen['revision']['aplicada'] = true;
            $resumen['revision']['resultado'] = $resultado;
            $importacion->resumen = $resumen;
            $importacion->save();

            return $resultado;
        });
    }

    /**
     * @return array{conteos: array<string, int>, bloqueos: list<array{codigo: string, filas: int}>, avisos: list<array{codigo: string, filas: int}>, muestras: list<array{numero_cliente: string, nombre: string, motivo: string}>, hash: string, huella: string, numeros: list<string>, estado_clientes: array<string, ?array{lista: string, monto: string, inactivo: int, updated_at: string}>, bloqueado: bool}
     */
    private function analizar(ImportacionCliente $importacion, bool $persistirFilas = false): array
    {
        $this->loteRevision = [];
        $this->hechasRevision = 0;
        $path = Storage::disk('local')->path($importacion->ruta_almacenamiento);
        $validado = $this->validador->leer($path);
        $mapa = $this->importador->cargarMapaVendedoras();
        $listas = $this->importador->listasParaRevision();
        $importaCodigo = in_array('codigo_lista', $validado['headers'], true);
        $importaFecha = in_array('fecha_corte', $validado['headers'], true) || in_array('fecha_exportacion', $validado['headers'], true);

        $conteos = array_fill_keys([
            'filas', 'nuevos', 'actualizaciones', 'sin_cambios', 'errores', 'cambios_lista', 'descensos',
            'activaciones', 'inactivaciones', 'cambios_monto', 'afectados',
        ], 0);
        $conteos['filas'] = count($validado['filas']);
        $bloqueos = [];
        $avisos = [];
        $muestras = [];
        $numeros = [];
        $lineas = [];
        $estadoClientes = [];

        $clientes = Cliente::query()
            ->whereIn('numero_cliente', array_values(array_filter(array_map(
                fn (array $fila) => $fila['data']['numero_cliente'] ?? '',
                $validado['filas'],
            ))))
            ->get()
            ->keyBy('numero_cliente');
        $cortes = HistorialMontoCliente::query()
            ->select('cliente_id', DB::raw('max(created_at) as corte'))
            ->whereIn('cliente_id', $clientes->pluck('id'))
            ->groupBy('cliente_id')
            ->pluck('corte', 'cliente_id');

        $sha = is_file($path) ? (hash_file('sha256', $path) ?: '') : '';
        if ($persistirFilas) {
            FilaRevisionImportacionCliente::query()->where('importacion_cliente_id', $importacion->id)->delete();
            ImportacionCliente::query()->whereKey($importacion->id)->update([
                'filas_leidas' => $conteos['filas'],
                'filas_procesadas' => 0,
            ]);
        }
        foreach ($validado['filas'] as $fila) {
            $numero = $fila['data']['numero_cliente'] ?? '';
            if ($numero !== '') {
                $numeros[] = $numero;
            }
            $antesModelo = $clientes->get($numero);
            $antes = $antesModelo?->getAttributes();
            if ($numero !== '' && ! array_key_exists($numero, $estadoClientes)) {
                $estadoClientes[$numero] = $this->estadoCliente($antes);
            }
            if ($fila['duplicada']) {
                $conteos['sin_cambios']++;
                $lineas[] = $fila['numero_fila'].'|'.$numero.'|sin_cambios|||';
                $this->anotarFila($persistirFilas, $importacion, $fila, $numero, (string) ($antes['nombre'] ?? ''), 'sin_cambios', null, null, null, false, false, $conteos['filas']);

                continue;
            }
            $historial = [];
            $inactivos = 0;
            $alertas = [];
            $coleccion = collect($antesModelo ? [$numero => $antesModelo] : []);
            $evaluacion = null;
            DB::beginTransaction();
            try {
                $cambio = $this->procesador->ejecutar(
                    $fila['data'], $listas, $mapa, $coleccion, $historial, $importaCodigo, $inactivos, $alertas,
                    null, null, false,
                );
                $despues = $coleccion->get($numero);
                $evaluacion = $this->evaluarFila($fila, $antes, $despues, $cambio, $importaCodigo, $importaFecha, $cortes);
                $lineas[] = $this->lineaProyeccion($fila, $despues, $cambio, $listas, $evaluacion['categoria']);
            } catch (ValidationException $e) {
                $conteos['errores']++;
                $mensaje = (string) collect($e->errors())->flatten()->first();
                $lineas[] = $fila['numero_fila'].'|'.$numero.'|error|'.$mensaje;
                if ($this->errorInvalidaArchivo($e)) {
                    $bloqueos['archivo_invalido'] = ($bloqueos['archivo_invalido'] ?? 0) + 1;
                }
                $nombreError = $fila['data']['nombre'] ?? (is_array($antes) ? ($antes['nombre'] ?? '') : '');
                $this->muestra($muestras, $conteos, $numero, (string) $nombreError, 'Fila con error: '.$mensaje);
                $evaluacion = [
                    'categoria' => 'error',
                    'motivo' => 'Fila con error: '.$mensaje,
                    'nombre' => (string) $nombreError,
                    'bloqueos' => $this->errorInvalidaArchivo($e) ? ['archivo_invalido'] : [],
                ];
            } finally {
                DB::rollBack();
            }

            if (($evaluacion['categoria'] ?? null) === 'error') {
                $this->anotarFila(
                    $persistirFilas, $importacion, $fila, $numero, (string) ($evaluacion['nombre'] ?? ''),
                    'error', $evaluacion['motivo'], null, null, $evaluacion['bloqueos'] !== [], false, $conteos['filas'],
                );

                continue;
            }

            foreach ($evaluacion['conteos'] as $clave => $suma) {
                $conteos[$clave] += $suma;
            }
            foreach ($evaluacion['bloqueos'] as $codigo) {
                $bloqueos[$codigo] = ($bloqueos[$codigo] ?? 0) + 1;
            }
            foreach ($evaluacion['avisos'] as $codigo) {
                $avisos[$codigo] = ($avisos[$codigo] ?? 0) + 1;
            }
            if ($evaluacion['motivo'] !== null) {
                $this->muestra($muestras, $conteos, $numero, $despues?->nombre ?? ($antes['nombre'] ?? ''), $evaluacion['motivo']);
            }
            $this->anotarFila(
                $persistirFilas, $importacion, $fila, $numero,
                (string) ($despues?->nombre ?? ($antes['nombre'] ?? '')),
                $evaluacion['categoria'], $evaluacion['motivo'], $evaluacion['monto_anterior'] ?? null, $evaluacion['monto_nuevo'] ?? null,
                $evaluacion['bloqueos'] !== [], $evaluacion['avisos'] !== [], $conteos['filas'],
            );
        }
        $this->vaciarRevision($persistirFilas, $importacion, $conteos['filas']);

        if ($this->archivoRepetido($importacion, $sha)) {
            $bloqueos['archivo_repetido'] = 1;
        }
        $codigos = array_keys($bloqueos);
        sort($codigos);
        $lineas[] = 'bloqueos:'.implode(',', $codigos);
        $numeros = array_values(array_unique($numeros));

        return [
            'conteos' => $conteos,
            'bloqueos' => $this->listar($bloqueos),
            'avisos' => $this->listar($avisos),
            'muestras' => $muestras,
            'hash' => hash('sha256', implode("\n", $lineas)),
            'huella' => $this->huella($numeros),
            'numeros' => $numeros,
            'estado_clientes' => $estadoClientes,
            'bloqueado' => $bloqueos !== [],
        ];
    }

    private function vistaBloqueada(ImportacionCliente $importacion, string $sha, string $motivo): array
    {
        $conteos = array_fill_keys([
            'filas', 'nuevos', 'actualizaciones', 'sin_cambios', 'errores', 'cambios_lista', 'descensos',
            'activaciones', 'inactivaciones', 'cambios_monto', 'afectados',
        ], 0);
        $revision = [
            'archivo_sha256' => $sha,
            'huella_clientes' => hash('sha256', ''),
            'numeros' => [],
            'token' => Str::random(40),
            'bloqueado' => true,
            'aplicada' => false,
            'proyeccion' => hash('sha256', 'archivo_invalido|'.$motivo),
            'catalogo' => hash('sha256', ''),
            'estado_clientes' => [],
        ];
        $importacion->estado = 'previsualizada';
        $importacion->resumen = [
            'revision' => $revision,
            'conteos' => $conteos,
            'bloqueos' => [['codigo' => 'archivo_invalido', 'filas' => 1]],
            'avisos' => [],
            'muestras' => [['numero_cliente' => '', 'nombre' => '', 'motivo' => $motivo]],
        ];
        $importacion->save();
        FilaRevisionImportacionCliente::query()->where('importacion_cliente_id', $importacion->id)->delete();
        FilaRevisionImportacionCliente::query()->insert([[
            'importacion_cliente_id' => $importacion->id,
            'numero_fila' => 0,
            'numero_cliente' => null,
            'nombre' => null,
            'categoria' => 'error',
            'motivo' => $motivo,
            'monto_anterior' => null,
            'monto_nuevo' => null,
            'es_bloqueo' => 1,
            'es_aviso' => 0,
        ]]);

        return [
            'importacion_id' => $importacion->id,
            'token' => $revision['token'],
            'bloqueado' => true,
            'bloqueos' => [['codigo' => 'archivo_invalido', 'filas' => 1]],
            'avisos' => [],
            'conteos' => $conteos,
            'muestras' => [['numero_cliente' => '', 'nombre' => '', 'motivo' => $motivo]],
        ];
    }

    private function errorInvalidaArchivo(ValidationException $e): bool
    {
        if (isset($e->errors()['archivo'])) {
            return true;
        }
        foreach (collect($e->errors())->flatten() as $mensaje) {
            if (str_contains((string) $mensaje, 'Falta la lista técnica PUBLICO GENERAL')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @return ?array{lista: string, monto: string, inactivo: int, updated_at: string, modalidad: string, origen: string, conversion_solicitud_id: string}
     */
    private function estadoCliente(?array $antes): ?array
    {
        if ($antes === null) {
            return null;
        }

        return [
            'lista' => (string) ($antes['lista_actual_id'] ?? ''),
            'monto' => bcadd((string) ($antes['monto_venta_actual'] ?? '0'), '0', 2),
            'inactivo' => (int) ($antes['es_inactivo'] ?? 0),
            'updated_at' => (string) ($antes['updated_at'] ?? ''),
            'modalidad' => (string) ($antes['modalidad_comercial'] ?? ''),
            'origen' => (string) ($antes['modalidad_origen'] ?? ''),
            'conversion_solicitud_id' => (string) ($antes['conversion_solicitud_id'] ?? ''),
        ];
    }

    private function lineaProyeccion(array $fila, ?Cliente $despues, ?array $cambio, $listas, string $categoria): string
    {
        $lista = $listas->first(fn ($item) => (string) $item->id === (string) $despues?->lista_actual_id);

        return implode('|', [
            $fila['numero_fila'],
            $fila['data']['numero_cliente'] ?? '',
            $categoria,
            (string) ($lista?->nombre ?? ''),
            $despues ? bcadd((string) $despues->monto_venta_actual, '0', 2) : '',
            $despues ? (int) $despues->es_inactivo : '',
            $cambio['tipo_cambio'] ?? '',
            (string) ($despues->modalidad_comercial ?? ''),
            (string) ($despues->modalidad_origen ?? ''),
            (string) ($despues->conversion_solicitud_id ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, int>  $cortes
     * @return array{conteos: array<string, int>, bloqueos: list<string>, avisos: list<string>, motivo: ?string, categoria: string}
     */
    private function evaluarFila(array $fila, ?array $antes, ?Cliente $despues, ?array $cambio, bool $importaCodigo, bool $importaFecha, $cortes): array
    {
        $conteos = array_fill_keys(['nuevos', 'actualizaciones', 'sin_cambios', 'cambios_lista', 'descensos', 'activaciones', 'inactivaciones', 'cambios_monto'], 0);
        $bloqueos = [];
        $avisos = [];
        $motivos = [];
        $codigo = trim($fila['data']['codigo_lista'] ?? '');
        $nuevo = $antes === null;
        if ($nuevo) {
            $conteos['nuevos']++;
            $motivos[] = 'Cliente nuevo.';
            $categoria = 'nuevo';
        } elseif ($despues && ($despues->wasChanged() || $despues->isDirty())) {
            $conteos['actualizaciones']++;
            $categoria = 'actualizacion';
        } else {
            $conteos['sin_cambios']++;
            $categoria = 'sin_cambios';
        }

        $listaAntes = $antes['lista_actual_id'] ?? null;
        $listaDespues = $despues?->lista_actual_id;
        $inactivoAntes = (bool) ($antes['es_inactivo'] ?? false);
        $inactivoDespues = (bool) ($despues?->es_inactivo ?? false);
        if ($listaAntes != $listaDespues) {
            $conteos['cambios_lista']++;
        }
        if (($cambio['tipo_cambio'] ?? '') === 'descenso') {
            $conteos['descensos']++;
            $avisos[] = 'descenso_lista';
            $motivos[] = 'La lista bajaría.';
        }
        if ($inactivoAntes && ! $inactivoDespues) {
            $conteos['activaciones']++;
            $motivos[] = 'El cliente pasaría a activo.';
        }
        if ($inactivoDespues && ($nuevo || ! $inactivoAntes)) {
            $conteos['inactivaciones']++;
            $bloqueos[] = 'inactivacion';
            $motivos[] = 'La fila inactivaría al cliente. GELIA no acepta esa baja.';
        }
        if ($importaCodigo && $codigo === '' && $antes && ($listaAntes != $listaDespues || $inactivoAntes !== $inactivoDespues || (string) ($antes['modalidad_comercial'] ?? '') !== (string) ($despues->modalidad_comercial ?? ''))) {
            $bloqueos[] = 'codigo_vacio';
            $motivos[] = 'Un código vacío cambiaría la lista, el estado o la modalidad.';
        } elseif ($importaCodigo && $codigo === '' && $antes) {
            $motivos[] = 'El código vacío conserva la lista, el estado y la modalidad.';
        }
        if ($despues?->discrepanciaModalidad === 'lista_mayorista_sin_conversion') {
            $avisos[] = 'lista_mayorista_sin_conversion';
            $motivos[] = 'El código mayorista no cambia a un cliente de menudeo sin conversión autorizada.';
        }
        if ($despues?->discrepanciaModalidad === 'conversion_autorizada') {
            $avisos[] = 'conversion_autorizada';
            $motivos[] = 'La importación no deshace una conversión mayorista autorizada.';
        }
        if (! empty($antes['lista_bloqueada']) && $listaAntes != $listaDespues) {
            $bloqueos[] = 'lista_bloqueada';
            $motivos[] = 'La lista protegida sería reemplazada.';
        }

        $montoAntes = $antes['monto_venta_actual'] ?? null;
        $montoDespues = $despues?->monto_venta_actual;
        if ($montoAntes !== null && $montoDespues !== null && bccomp((string) $montoAntes, (string) $montoDespues, 2) !== 0) {
            $conteos['cambios_monto']++;
            $delta = bcsub((string) $montoDespues, (string) $montoAntes, 2);
            if (str_starts_with($delta, '-')) {
                $delta = substr($delta, 1);
            }
            if (bccomp($delta, self::UMBRAL_DIFERENCIA_MONTO, 2) >= 0) {
                $avisos[] = 'diferencia_monto';
                $motivos[] = 'La diferencia de monto supera 1000.';
            }
            if ($this->archivoAnterior($fila, $antes, $importaFecha, $cortes)) {
                $bloqueos[] = 'archivo_anterior';
                $motivos[] = 'Hay un monto más reciente que la fecha del archivo.';
            }
        }

        return [
            'conteos' => $conteos,
            'bloqueos' => $bloqueos,
            'avisos' => $avisos,
            'motivo' => $motivos === [] ? null : implode(' ', $motivos),
            'categoria' => $categoria,
            'monto_anterior' => $montoAntes !== null ? bcadd((string) $montoAntes, '0', 2) : null,
            'monto_nuevo' => $montoDespues !== null ? bcadd((string) $montoDespues, '0', 2) : null,
        ];
    }

    private function archivoAnterior(array $fila, array $antes, bool $importaFecha, $cortes): bool
    {
        if (! $importaFecha || ! isset($antes['id'])) {
            return false;
        }
        $texto = trim($fila['data']['fecha_corte'] ?? $fila['data']['fecha_exportacion'] ?? '');
        $fecha = $this->fecha($texto);
        $corte = $cortes[$antes['id']] ?? null;
        if ($fecha === null || $corte === null) {
            return false;
        }

        return $fecha < strtotime((string) $corte);
    }

    private function fecha(string $texto): ?int
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d'] as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat('!'.$formato, $texto);
            if ($fecha instanceof \DateTimeImmutable) {
                return $fecha->getTimestamp();
            }
        }

        return null;
    }

    private function archivoRepetido(ImportacionCliente $importacion, string $sha): bool
    {
        return ImportacionCliente::query()
            ->where('id', '!=', $importacion->id)
            ->whereIn('estado', ['completo', 'parcial'])
            ->get(['resumen'])
            ->contains(fn (ImportacionCliente $otra) => ($otra->resumen['revision']['archivo_sha256'] ?? null) === $sha);
    }

    /**
     * @param  list<string>  $numeros
     */
    private function huella(array $numeros): string
    {
        $lineas = Cliente::query()
            ->whereIn('numero_cliente', $numeros)
            ->orderBy('numero_cliente')
            ->get(['numero_cliente', 'lista_actual_id', 'monto_venta_actual', 'es_inactivo', 'updated_at', 'modalidad_comercial', 'modalidad_origen', 'conversion_solicitud_id'])
            ->map(fn (Cliente $cliente) => implode('|', [
                $cliente->numero_cliente,
                $cliente->lista_actual_id,
                $cliente->monto_venta_actual,
                (int) $cliente->es_inactivo,
                (string) $cliente->updated_at,
                (string) ($cliente->modalidad_comercial ?? ''),
                (string) ($cliente->modalidad_origen ?? ''),
                (string) ($cliente->conversion_solicitud_id ?? ''),
            ]))
            ->all();

        return hash('sha256', implode("\n", $lineas));
    }

    /**
     * @param  list<array{numero_cliente: string, nombre: string, motivo: string}>  $muestras
     * @param  array<string, int>  $conteos
     */
    private function muestra(array &$muestras, array &$conteos, string $numero, string $nombre, string $motivo): void
    {
        $conteos['afectados']++;
        if (count($muestras) < 25) {
            $muestras[] = ['numero_cliente' => $numero, 'nombre' => $nombre, 'motivo' => $motivo];
        }
    }

    /**
     * @param  array<string, int>  $conteo
     * @return list<array{codigo: string, filas: int}>
     */
    private function listar(array $conteo): array
    {
        $lista = [];
        foreach ($conteo as $codigo => $filas) {
            $lista[] = ['codigo' => $codigo, 'filas' => $filas];
        }

        return $lista;
    }

    private function anotarFila(
        bool $persistir,
        ImportacionCliente $importacion,
        array $fila,
        string $numero,
        string $nombre,
        string $categoria,
        ?string $motivo,
        ?string $montoAnterior,
        ?string $montoNuevo,
        bool $bloqueo,
        bool $aviso,
        int $total,
    ): void {
        $this->hechasRevision++;
        if (! $persistir) {
            return;
        }
        $this->loteRevision[] = [
            'importacion_cliente_id' => $importacion->id,
            'numero_fila' => (int) $fila['numero_fila'],
            'numero_cliente' => $numero !== '' ? $numero : null,
            'nombre' => $nombre !== '' ? $nombre : null,
            'categoria' => $categoria,
            'motivo' => $motivo,
            'monto_anterior' => $montoAnterior,
            'monto_nuevo' => $montoNuevo,
            'es_bloqueo' => $bloqueo ? 1 : 0,
            'es_aviso' => $aviso ? 1 : 0,
        ];
        if (count($this->loteRevision) >= 200 || $this->hechasRevision === $total || $this->hechasRevision % 50 === 0) {
            $this->vaciarRevision(true, $importacion, $total);
        }
    }

    private function vaciarRevision(bool $persistir, ImportacionCliente $importacion, int $total): void
    {
        if (! $persistir || $this->loteRevision === []) {
            return;
        }
        FilaRevisionImportacionCliente::query()->insert($this->loteRevision);
        $this->loteRevision = [];
        ImportacionCliente::query()->whereKey($importacion->id)->update([
            'filas_leidas' => $total,
            'filas_procesadas' => $this->hechasRevision,
        ]);
    }
}
