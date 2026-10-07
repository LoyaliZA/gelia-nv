<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoImportacion;
use App\Models\Escalonamiento\EscalonamientoImportacionFila;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolverIncidenciaEscalonamiento
{
    public function __construct(
        private ListaPublicoGeneralEscalonamiento $listaPublicoGeneral,
        private SincronizarIncidenciaDivergenciaLista $divergenciaLista,
        private ImportarDocumentosEscalonamiento $importar,
    ) {}

    /**
     * @return list<array{id: string, etiqueta: string}>
     */
    public function accionesPara(EscalonamientoIncidencia $incidencia): array
    {
        if ($incidencia->estado !== 'abierta') {
            return [];
        }

        $nota = ['id' => 'nota', 'etiqueta' => 'Cerrar con nota'];

        return match ($incidencia->codigo) {
            'cliente_no_identificado' => $this->accionesClienteNoIdentificado($incidencia, $nota),
            'divergencia_lista_operativa' => $this->accionesDivergencia($incidencia, $nota),
            default => [$nota],
        };
    }

    /**
     * @param  array{resolucion?: string, cliente_id?: int|null}  $datos
     */
    public function resolver(EscalonamientoIncidencia $incidencia, string $accion, ?User $usuario, array $datos = []): string
    {
        return DB::transaction(function () use ($incidencia, $accion, $usuario, $datos) {
            $incidencia = EscalonamientoIncidencia::query()->lockForUpdate()->findOrFail($incidencia->id);

            return $this->aplicar($incidencia, $accion, $usuario, $datos);
        });
    }

    /**
     * @param  array{resolucion?: string}  $datos
     * @return array{aplicadas: int, omitidas: list<array{id: int, motivo: string}>, mensaje: string}
     */
    public function resolverLote(EscalonamientoPeriodo $periodo, string $codigo, string $accion, ?User $usuario, array $datos = []): array
    {
        if (! $this->accionMasivaPermitida($codigo, $accion)) {
            throw ValidationException::withMessages([
                'accion' => 'Esa acción no se aplica en lote para este tipo de incidencia.',
            ]);
        }

        $incidencias = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('codigo', $codigo)
            ->where('estado', 'abierta')
            ->orderBy('id')
            ->get();

        $aplicadas = 0;
        $omitidas = [];
        foreach ($incidencias as $incidencia) {
            try {
                $this->resolver($incidencia, $accion, $usuario, $datos);
                $aplicadas++;
            } catch (ValidationException $excepcion) {
                $omitidas[] = [
                    'id' => $incidencia->id,
                    'motivo' => (string) collect($excepcion->errors())->flatten()->first(),
                ];
            }
        }

        $mensaje = "Se aplicó la resolución en {$aplicadas} incidencia(s).";
        if ($omitidas !== []) {
            $mensaje .= ' Quedaron '.count($omitidas).' sin cambio.';
        }

        return [
            'aplicadas' => $aplicadas,
            'omitidas' => $omitidas,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @param  list<int>  $numerosFila
     * @return array{creados: int, filas: int, omitidas: int, mensaje: string}
     */
    public function resolverPrevisualizacion(
        EscalonamientoImportacion $importacion,
        string $accion,
        ?User $usuario,
        array $numerosFila = [],
    ): array {
        if (! in_array($accion, ['crear_cliente', 'crear_cliente_y_registrar'], true)) {
            throw ValidationException::withMessages([
                'accion' => 'En la previsualización solo se pueden crear clientes faltantes.',
            ]);
        }
        if ($importacion->estado !== 'previsualizada') {
            throw ValidationException::withMessages([
                'importacion' => 'Esa carga ya no está en previsualización.',
            ]);
        }

        $periodo = EscalonamientoPeriodo::query()->findOrFail($importacion->escalonamiento_periodo_id);
        $filas = $importacion->filas()->orderBy('numero_fila')->get()->filter(function (EscalonamientoImportacionFila $fila) use ($numerosFila) {
            $datos = is_array($fila->interpretacion) ? $fila->interpretacion : [];
            if ($fila->resultado !== 'incidencia' || ($datos['codigo_incidencia'] ?? '') !== 'cliente_no_identificado') {
                return false;
            }
            if ($numerosFila !== [] && ! in_array((int) $fila->numero_fila, $numerosFila, true)) {
                return false;
            }

            return true;
        });

        return DB::transaction(function () use ($filas, $periodo, $accion) {
            return $this->aplicarPrevisualizacion($filas, $periodo, $accion);
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EscalonamientoImportacionFila>  $filas
     * @return array{creados: int, filas: int, omitidas: int, mensaje: string}
     */
    private function aplicarPrevisualizacion($filas, EscalonamientoPeriodo $periodo, string $accion): array
    {
        $creados = 0;
        $tocadas = 0;
        $omitidas = 0;
        $porNumero = [];

        foreach ($filas as $fila) {
            $datos = is_array($fila->interpretacion) ? $fila->interpretacion : [];
            $numero = trim((string) ($datos['numero_cliente'] ?? ''));
            if ($numero === '') {
                $omitidas++;

                continue;
            }
            $porNumero[$numero]['nombre'] = trim((string) ($datos['nombre'] ?? ''));
            $porNumero[$numero]['filas'][] = $fila;
        }

        foreach ($porNumero as $numero => $grupo) {
            $antes = Cliente::query()->where('numero_cliente', $numero)->exists();
            $this->asegurarCliente((string) $numero, (string) ($grupo['nombre'] ?? ''));
            if (! $antes) {
                $creados++;
            }

            foreach ($grupo['filas'] as $fila) {
                $datos = is_array($fila->interpretacion) ? $fila->interpretacion : [];
                if ($accion === 'crear_cliente') {
                    $datos['omitir_en_confirmacion'] = true;
                    $datos['motivo_omision'] = 'Cliente creado en la base. El documento no se registra en esta carga.';
                    $fila->interpretacion = $datos;
                    $fila->resultado = 'omitida';
                    $fila->motivo = $datos['motivo_omision'];
                    $fila->save();
                } else {
                    $resultado = $this->importar->reinterpretarFila($periodo, $datos);
                    $fila->resultado = $resultado['resultado'];
                    $fila->motivo = $resultado['motivo'];
                    $fila->interpretacion = $resultado['datos'];
                    $fila->save();
                }
                $tocadas++;
            }
        }

        if ($accion === 'crear_cliente') {
            $mensaje = "Se agregaron {$creados} cliente(s) a la base. {$tocadas} documento(s) quedaron fuera de esta carga.";
        } else {
            $mensaje = "Se agregaron {$creados} cliente(s). {$tocadas} fila(s) quedaron listas para confirmar la carga.";
        }
        if ($omitidas > 0) {
            $mensaje .= " {$omitidas} fila(s) no tienen número de cliente: vincúlalas con un cliente existente desde Incidencias después de confirmar, o corrige el archivo.";
        }

        return [
            'creados' => $creados,
            'filas' => $tocadas,
            'omitidas' => $omitidas,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @param  array{id: string, etiqueta: string}  $nota
     * @return list<array{id: string, etiqueta: string}>
     */
    private function accionesClienteNoIdentificado(EscalonamientoIncidencia $incidencia, array $nota): array
    {
        $numero = $this->numeroDesde($incidencia);
        $nombre = $this->nombreDesde($incidencia);
        $acciones = [];
        if ($numero !== '' || $nombre !== '') {
            $acciones[] = ['id' => 'crear_cliente', 'etiqueta' => 'Agregar a la base de datos'];
            $acciones[] = ['id' => 'crear_cliente_y_registrar', 'etiqueta' => 'Agregar a la base y registrar documentos'];
        }
        $acciones[] = ['id' => 'vincular_cliente', 'etiqueta' => 'Vincular con cliente existente'];
        $acciones[] = ['id' => 'vincular_cliente_y_registrar', 'etiqueta' => 'Vincular y registrar documentos'];
        $acciones[] = $nota;

        return $acciones;
    }

    /**
     * @param  array{id: string, etiqueta: string}  $nota
     * @return list<array{id: string, etiqueta: string}>
     */
    private function accionesDivergencia(EscalonamientoIncidencia $incidencia, array $nota): array
    {
        $incidencia->loadMissing('cliente:id,lista_bloqueada');
        if ($incidencia->cliente?->lista_bloqueada) {
            return [$nota];
        }

        return [
            ['id' => 'alinear_lista_operativa', 'etiqueta' => 'Actualizar monto y lista vigente'],
            $nota,
        ];
    }

    private function accionMasivaPermitida(string $codigo, string $accion): bool
    {
        return match ($codigo) {
            'cliente_no_identificado' => in_array($accion, ['crear_cliente', 'crear_cliente_y_registrar', 'nota'], true),
            'divergencia_lista_operativa' => in_array($accion, ['alinear_lista_operativa', 'nota'], true),
            default => $accion === 'nota',
        };
    }

    /**
     * @param  array{resolucion?: string, cliente_id?: int|null, numero_cliente?: string|null}  $datos
     */
    private function aplicar(EscalonamientoIncidencia $incidencia, string $accion, ?User $usuario, array $datos): string
    {
        if ($incidencia->estado !== 'abierta') {
            throw ValidationException::withMessages([
                'accion' => 'La incidencia ya estaba resuelta.',
            ]);
        }

        $incidencia->loadMissing('cliente:id,numero_cliente,nombre,lista_actual_id,lista_bloqueada,monto_venta_actual');
        if ($accion === 'alinear_lista_operativa' && $incidencia->cliente?->lista_bloqueada) {
            throw ValidationException::withMessages([
                'accion' => 'La lista de este cliente está protegida. La divergencia se mantiene hasta quitar esa protección.',
            ]);
        }

        $permitidas = array_column($this->accionesPara($incidencia), 'id');
        if (! in_array($accion, $permitidas, true)) {
            throw ValidationException::withMessages([
                'accion' => 'Esa resolución no aplica a esta incidencia.',
            ]);
        }

        return match ($accion) {
            'nota' => $this->cerrarConNota($incidencia, (string) ($datos['resolucion'] ?? ''), $usuario),
            'crear_cliente' => $this->crearCliente($incidencia, $usuario, false, $datos),
            'crear_cliente_y_registrar' => $this->crearCliente($incidencia, $usuario, true, $datos),
            'vincular_cliente' => $this->vincularCliente($incidencia, (int) ($datos['cliente_id'] ?? 0), $usuario, false),
            'vincular_cliente_y_registrar' => $this->vincularCliente($incidencia, (int) ($datos['cliente_id'] ?? 0), $usuario, true),
            'alinear_lista_operativa' => $this->alinearLista($incidencia, $usuario),
            default => throw ValidationException::withMessages([
                'accion' => 'Resolución no reconocida.',
            ]),
        };
    }

    private function cerrarConNota(EscalonamientoIncidencia $incidencia, string $nota, ?User $usuario): string
    {
        $nota = trim($nota);
        if (mb_strlen($nota) < 3) {
            throw ValidationException::withMessages([
                'resolucion' => 'La nota debe tener al menos 3 caracteres.',
            ]);
        }

        $this->cerrar($incidencia, $nota, $usuario);

        return 'Incidencia cerrada con nota.';
    }

    /**
     * @param  array{numero_cliente?: string|null}  $datos
     */
    private function crearCliente(EscalonamientoIncidencia $incidencia, ?User $usuario, bool $registrar, array $datos = []): string
    {
        $numero = $this->numeroDesde($incidencia);
        if ($numero === '') {
            $numero = trim((string) ($datos['numero_cliente'] ?? ''));
        }
        $nombre = $this->nombreDesde($incidencia);
        if ($numero === '') {
            throw ValidationException::withMessages([
                'numero_cliente' => 'Indica el número de cliente para agregarlo a la base.',
            ]);
        }

        $cliente = $this->asegurarCliente($numero, $nombre);
        $incidencia->cliente_id = $cliente->id;
        $incidencia->save();

        if ($registrar) {
            $this->registrarDocumentos($incidencia, $cliente, $usuario);
        }

        $texto = $registrar
            ? 'Se agregó el cliente '.$numero.' a la base y se registraron sus documentos.'
            : 'Se agregó el cliente '.$numero.' a la base. Los documentos de esta incidencia no se registraron.';
        $this->cerrar($incidencia, $texto, $usuario);

        return $texto;
    }

    private function vincularCliente(EscalonamientoIncidencia $incidencia, int $clienteId, ?User $usuario, bool $registrar): string
    {
        $cliente = Cliente::query()->find($clienteId);
        if (! $cliente) {
            throw ValidationException::withMessages([
                'cliente_id' => 'Selecciona un cliente existente.',
            ]);
        }

        $incidencia->cliente_id = $cliente->id;
        $incidencia->save();

        if ($registrar) {
            $this->registrarDocumentos($incidencia, $cliente, $usuario);
        }

        $texto = $registrar
            ? 'Se vincularon los documentos al cliente '.$cliente->numero_cliente.'.'
            : 'Se vinculó la incidencia al cliente '.$cliente->numero_cliente.' sin registrar documentos.';
        $this->cerrar($incidencia, $texto, $usuario);

        return $texto;
    }

    private function alinearLista(EscalonamientoIncidencia $incidencia, ?User $usuario): string
    {
        $cliente = $incidencia->cliente;
        if (! $cliente) {
            throw ValidationException::withMessages([
                'accion' => 'La incidencia no tiene cliente para alinear la lista.',
            ]);
        }
        if ($cliente->lista_bloqueada) {
            throw ValidationException::withMessages([
                'accion' => 'La lista de este cliente está protegida. La divergencia se mantiene hasta quitar esa protección.',
            ]);
        }

        $periodo = EscalonamientoPeriodo::query()->find($incidencia->escalonamiento_periodo_id);
        $resumen = $periodo
            ? EscalonamientoResumenCliente::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->where('cliente_id', $cliente->id)
                ->first()
            : null;
        if (! $periodo || ! $resumen || ! $resumen->lista_vigente_id) {
            throw ValidationException::withMessages([
                'accion' => 'No hay lista vigente del módulo para copiar a la lista operativa.',
            ]);
        }

        $cliente->lista_actual_id = (int) $resumen->lista_vigente_id;
        $cliente->monto_venta_actual = $resumen->acumulado;
        $cliente->save();
        $this->divergenciaLista->sincronizar($periodo, $cliente->fresh(), $resumen->fresh());

        $incidencia->refresh();
        $texto = 'Se actualizó el monto y la lista operativa con la lista vigente del módulo.';
        if ($incidencia->estado === 'resuelta') {
            $incidencia->resolucion = $texto;
            $incidencia->resuelto_por_user_id = $usuario?->id;
            $incidencia->resuelto_en = $incidencia->resuelto_en ?? now();
            $incidencia->save();
        } else {
            $this->cerrar($incidencia, $texto, $usuario);
        }

        return $texto;
    }

    private function asegurarCliente(string $numero, string $nombre): Cliente
    {
        $existente = Cliente::query()->where('numero_cliente', $numero)->first();
        if ($existente) {
            return $existente;
        }

        try {
            $listaId = $this->listaPublicoGeneral->id();
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'accion' => 'No se pudo crear el cliente porque no hay una lista Público General única.',
            ]);
        }

        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => $nombre !== '' ? $nombre : 'Cliente '.$numero,
            'lista_actual_id' => $listaId,
            'monto_venta_actual' => 0,
            'es_heredado' => false,
            'es_inactivo' => false,
        ]);
    }

    private function registrarDocumentos(EscalonamientoIncidencia $incidencia, Cliente $cliente, ?User $usuario): void
    {
        $periodo = EscalonamientoPeriodo::query()->find($incidencia->escalonamiento_periodo_id);
        if (! $periodo) {
            throw ValidationException::withMessages([
                'accion' => 'La incidencia no tiene período para registrar documentos.',
            ]);
        }

        $documentos = $this->documentosDesde($incidencia);
        if ($documentos === []) {
            throw ValidationException::withMessages([
                'accion' => 'No hay documentos guardados en esta incidencia para registrar.',
            ]);
        }

        $pendientes = [];
        $errores = [];
        foreach ($documentos as $documento) {
            if (! is_array($documento)) {
                continue;
            }
            $documento['numero_cliente'] = $cliente->numero_cliente;
            if (trim((string) ($documento['nombre'] ?? '')) === '') {
                $documento['nombre'] = $cliente->nombre;
            }
            $resultado = $this->importar->registrarFilaEnPeriodo($periodo, $documento, $usuario);
            if ($resultado['resultado'] === 'incidencia') {
                $pendientes[] = $documento;
                $errores[] = (string) ($resultado['motivo'] ?? 'No se pudo registrar el documento.');
            }
        }

        if ($pendientes !== []) {
            $contexto = is_array($incidencia->contexto) ? $incidencia->contexto : [];
            $contexto['documentos'] = $pendientes;
            $incidencia->contexto = $contexto;
            $incidencia->save();

            throw ValidationException::withMessages([
                'accion' => implode(' ', array_unique($errores)),
            ]);
        }
    }

    public function numeroDesde(EscalonamientoIncidencia $incidencia): string
    {
        $numero = trim((string) ($incidencia->contexto['numero_cliente'] ?? ''));
        if ($numero !== '') {
            return $numero;
        }

        if (preg_match('/número\s+([^\s.]+)/u', (string) $incidencia->motivo, $coincide)) {
            return $coincide[1];
        }

        return '';
    }

    public function nombreDesde(EscalonamientoIncidencia $incidencia): string
    {
        $nombre = trim((string) ($incidencia->contexto['nombre'] ?? ''));
        if ($nombre !== '') {
            return $nombre;
        }

        if (preg_match('/nombre\s+(.+?)\./u', (string) $incidencia->motivo, $coincide)) {
            return trim($coincide[1]);
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documentosDesde(EscalonamientoIncidencia $incidencia): array
    {
        $guardados = is_array($incidencia->contexto['documentos'] ?? null)
            ? $incidencia->contexto['documentos']
            : [];
        if ($guardados !== []) {
            return $guardados;
        }

        $numero = $this->numeroDesde($incidencia);
        $nombre = $this->nombreDesde($incidencia);
        if (($numero === '' && $nombre === '') || ! $incidencia->escalonamiento_periodo_id) {
            return [];
        }

        return EscalonamientoImportacionFila::query()
            ->whereHas('importacion', fn ($q) => $q->where('escalonamiento_periodo_id', $incidencia->escalonamiento_periodo_id))
            ->where('resultado', 'incidencia')
            ->orderBy('id')
            ->get()
            ->map(fn (EscalonamientoImportacionFila $fila) => is_array($fila->interpretacion) ? $fila->interpretacion : [])
            ->filter(function (array $datos) use ($numero, $nombre) {
                if ($numero !== '' && trim((string) ($datos['numero_cliente'] ?? '')) === $numero) {
                    return true;
                }

                return $numero === ''
                    && $nombre !== ''
                    && trim((string) ($datos['nombre'] ?? '')) === $nombre;
            })
            ->values()
            ->all();
    }

    private function cerrar(EscalonamientoIncidencia $incidencia, string $resolucion, ?User $usuario): void
    {
        $incidencia->estado = 'resuelta';
        $incidencia->resolucion = $resolucion;
        $incidencia->resuelto_en = now();
        $incidencia->resuelto_por_user_id = $usuario?->id;
        $incidencia->save();
    }
}
