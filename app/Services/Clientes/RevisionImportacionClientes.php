<?php

namespace App\Services\Clientes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
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

    public function __construct(
        private ImportarClientesWizerpService $importador,
        private ProcesarFilaClienteAction $procesador,
        private ValidarArchivoClientesWizerp $validador,
    ) {}

    public function previsualizar(ImportacionCliente $importacion): array
    {
        $path = Storage::disk('local')->path($importacion->ruta_almacenamiento);
        $validado = $this->validador->leer($path);
        $sha = hash_file('sha256', $path);
        $this->importador->sincronizarListasWizerp();
        $mapa = $this->importador->cargarMapaVendedoras();
        $listas = CatalogoListaDescuento::orderByDesc('monto_requerido')->get();
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
        $ids = [];

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

        foreach ($validado['filas'] as $fila) {
            $numero = $fila['data']['numero_cliente'] ?? '';
            if ($numero !== '') {
                $numeros[] = $numero;
            }
            if ($fila['duplicada']) {
                $conteos['sin_cambios']++;

                continue;
            }
            $antesModelo = $clientes->get($numero);
            $antes = $antesModelo?->getAttributes();
            $historial = [];
            $inactivos = 0;
            $alertas = [];
            $coleccion = collect($antesModelo ? [$numero => $antesModelo] : []);
            DB::beginTransaction();
            try {
                $cambio = $this->procesador->ejecutar(
                    $fila['data'], $listas, $mapa, $coleccion, $historial, $importaCodigo, $inactivos, $alertas,
                );
                $despues = $coleccion->get($numero);
                $evaluacion = $this->evaluarFila($fila, $antes, $despues, $cambio, $importaCodigo, $importaFecha, $cortes);
            } catch (ValidationException $e) {
                $conteos['errores']++;
                $nombreError = $fila['data']['nombre'] ?? (is_array($antes) ? ($antes['nombre'] ?? '') : '');
                $this->muestra($muestras, $conteos, $numero, (string) $nombreError, 'Fila con error: '.collect($e->errors())->flatten()->first());

                continue;
            } finally {
                DB::rollBack();
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
            if ($antesModelo) {
                $ids[] = $antesModelo->id;
            }
        }

        if ($this->archivoRepetido($importacion, $sha)) {
            $bloqueos['archivo_repetido'] = 1;
        }

        $revision = [
            'archivo_sha256' => $sha,
            'huella_clientes' => $this->huella($numeros),
            'numeros' => array_values(array_unique($numeros)),
            'token' => Str::random(40),
            'bloqueado' => $bloqueos !== [],
            'aplicada' => false,
        ];
        $importacion->estado = 'previsualizada';
        $importacion->resumen = ['revision' => $revision];
        $importacion->save();

        return [
            'importacion_id' => $importacion->id,
            'token' => $revision['token'],
            'bloqueado' => $revision['bloqueado'],
            'bloqueos' => $this->listar($bloqueos),
            'avisos' => $this->listar($avisos),
            'conteos' => $conteos,
            'muestras' => $muestras,
        ];
    }

    public function confirmar(ImportacionCliente $importacion, string $token): array
    {
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
        if (! is_file($path) || ! hash_equals((string) $revision['archivo_sha256'], hash_file('sha256', $path))) {
            throw ValidationException::withMessages(['archivo' => 'El archivo cambió después de la revisión. Hay que revisarlo de nuevo.']);
        }
        if (! hash_equals((string) $revision['huella_clientes'], $this->huella($revision['numeros'] ?? []))) {
            throw ValidationException::withMessages(['archivo' => 'El estado de los clientes cambió. Hay que revisar el archivo de nuevo.']);
        }

        $archivo = new UploadedFile($path, $importacion->nombre_archivo_original, 'text/csv', null, true);
        $resultado = $this->importador->ejecutar($archivo, $importacion->fresh());
        $importacion->refresh();
        $resumen = $importacion->resumen ?? [];
        $resumen['revision']['aplicada'] = true;
        $resumen['revision']['resultado'] = $resultado;
        $importacion->resumen = $resumen;
        $importacion->save();

        return $resultado;
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, int>  $cortes
     * @return array{conteos: array<string, int>, bloqueos: list<string>, avisos: list<string>, motivo: ?string}
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
        } elseif ($despues && $despues->wasChanged()) {
            $conteos['actualizaciones']++;
        } else {
            $conteos['sin_cambios']++;
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
        if ($importaCodigo && $codigo === '' && $antes && ($listaAntes != $listaDespues || $inactivoAntes !== $inactivoDespues)) {
            $bloqueos[] = 'codigo_vacio';
            $motivos[] = 'Un código vacío cambiaría la lista o el estado.';
        } elseif ($importaCodigo && $codigo === '' && $antes) {
            $motivos[] = 'El código vacío conserva la lista y el estado.';
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
            ->get(['numero_cliente', 'lista_actual_id', 'monto_venta_actual', 'es_inactivo', 'updated_at'])
            ->map(fn (Cliente $cliente) => implode('|', [
                $cliente->numero_cliente,
                $cliente->lista_actual_id,
                $cliente->monto_venta_actual,
                (int) $cliente->es_inactivo,
                (string) $cliente->updated_at,
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
}
