<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoAplicacionDevolucion;
use App\Models\Escalonamiento\DocumentoVentaRevision;
use App\Models\Escalonamiento\EscalonamientoImportacion;
use App\Models\Escalonamiento\EscalonamientoImportacionFila;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\User;
use App\Services\Escalonamiento\Excepciones\AcumuladoNegativoException;
use App\Services\Escalonamiento\Excepciones\PeriodoNoAbiertoException;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Rap2hpoutre\FastExcel\FastExcel;

class ImportarDocumentosEscalonamiento
{
    /** @var array<string, array<int, Cliente>> */
    private array $indiceNombres = [];

    private bool $indiceCargado = false;

    public function __construct(
        private RegistrarMovimiento $registrar,
        private ParticipacionClienteEscalonamiento $participacion,
        private RevertirAplicacionDevolucion $revertirAplicacion,
        private ResolverPeriodoDocumentoEscalonamiento $resolverPeriodo,
        private AsegurarPeriodoHistoricoEscalonamiento $asegurarHistorico,
        private AsegurarPeriodoFuturoEscalonamiento $asegurarFuturo,
        private MapearExpedienteRemisionReporte $mapearExpediente,
        private SincronizarExpedienteRemisionEscalonamiento $sincronizarExpediente,
    ) {}

    public function previsualizar(
        EscalonamientoPeriodo $periodo,
        UploadedFile $archivo,
        ?User $usuario,
        string $tipoDocumento,
    ): EscalonamientoImportacion
    {
        $this->exigirAbierto($periodo);
        $filas = $this->leer($archivo);
        if ($filas === []) {
            throw new InvalidArgumentException('El archivo no tiene filas para importar.');
        }

        $contenido = file_get_contents($archivo->getRealPath() ?: '');
        if ($contenido === false) {
            throw new InvalidArgumentException('No se pudo leer el archivo.');
        }

        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'csv');
        $hash = hash('sha256', $contenido);
        $ruta = 'escalonamiento/importaciones/'.$hash.'.'.$extension;
        Storage::disk('local')->put($ruta, $contenido);
        $esReporte = $this->esReporteRemisiones($filas);
        $this->reiniciarIndice();

        return DB::transaction(function () use ($periodo, $archivo, $usuario, $filas, $hash, $ruta, $esReporte, $tipoDocumento) {
            $importacion = EscalonamientoImportacion::create([
                'escalonamiento_periodo_id' => $periodo->id,
                'user_id' => $usuario?->id,
                'nombre_archivo' => $archivo->getClientOriginalName(),
                'tipo_documento' => $tipoDocumento,
                'hash' => $hash,
                'ruta' => $ruta,
                'estado' => 'previsualizada',
            ]);

            foreach ($filas as $fila) {
                $resultado = $this->procesar($periodo, $fila['datos'], false, $usuario?->id, 'archivo', $esReporte, $tipoDocumento);
                EscalonamientoImportacionFila::create([
                    'escalonamiento_importacion_id' => $importacion->id,
                    'numero_fila' => $fila['numero_fila'],
                    'resultado' => $resultado['resultado'],
                    'motivo' => $resultado['motivo'],
                    'interpretacion' => $resultado['datos'],
                ]);
            }

            return $importacion->load('filas');
        });
    }

    /**
     * @return array{mensaje: string, conteo: array<string, int>}
     */
    public function confirmar(int $importacionId, ?User $usuario): array
    {
        return DB::transaction(function () use ($importacionId, $usuario) {
            $importacion = EscalonamientoImportacion::query()->lockForUpdate()->findOrFail($importacionId);
            if ($importacion->estado === 'confirmada') {
                return [
                    'mensaje' => 'Esa carga ya estaba confirmada. Los saldos no volvieron a cambiar.',
                    'conteo' => [],
                ];
            }

            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($importacion->escalonamiento_periodo_id);
            $this->exigirAbierto($periodo);
            $this->reiniciarIndice();

            $tipoDocumento = (string) ($importacion->tipo_documento ?? '');

            foreach ($importacion->filas()->orderBy('numero_fila')->get() as $fila) {
                $datos = is_array($fila->interpretacion) ? $fila->interpretacion : [];
                if (! empty($datos['omitir_en_confirmacion'])) {
                    $fila->resultado = 'omitida';
                    $fila->motivo = (string) ($datos['motivo_omision'] ?? 'Documento omitido al resolver la incidencia.');
                    $fila->save();

                    continue;
                }
                $esReporte = (bool) ($datos['reporte_remisiones'] ?? false);
                $resultado = $this->procesar(
                    $periodo,
                    $datos,
                    true,
                    $usuario?->id,
                    (string) ($datos['origen'] ?? 'archivo'),
                    $esReporte,
                    $tipoDocumento !== '' ? $tipoDocumento : null,
                );
                $fila->resultado = $resultado['resultado'];
                $fila->motivo = $resultado['motivo'];
                $fila->save();
            }

            $importacion->estado = 'confirmada';
            $importacion->save();
            $periodo->fecha_corte = now();
            $periodo->save();

            $conteo = $importacion->filas()->get()->countBy('resultado')->all();

            return [
                'mensaje' => $this->mensaje($conteo),
                'conteo' => $conteo,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array{resultado: string, motivo: ?string}
     */
    public function capturar(EscalonamientoPeriodo $periodo, array $entrada, ?User $usuario): array
    {
        $this->exigirAbierto($periodo);
        $this->reiniciarIndice();

        return DB::transaction(function () use ($periodo, $entrada, $usuario) {
            $resultado = $this->procesar($periodo, $entrada, true, $usuario?->id, 'manual');
            $importacion = EscalonamientoImportacion::create([
                'escalonamiento_periodo_id' => $periodo->id,
                'user_id' => $usuario?->id,
                'nombre_archivo' => 'captura-manual',
                'hash' => hash('sha256', (string) json_encode($resultado['datos'])),
                'ruta' => null,
                'estado' => 'confirmada',
            ]);
            EscalonamientoImportacionFila::create([
                'escalonamiento_importacion_id' => $importacion->id,
                'numero_fila' => 1,
                'resultado' => $resultado['resultado'],
                'motivo' => $resultado['motivo'],
                'interpretacion' => $resultado['datos'],
            ]);
            $periodo->fecha_corte = now();
            $periodo->save();

            return $resultado;
        });
    }

    /**
     * @param  array<string, mixed>  $bruto
     * @return array{resultado: string, motivo: ?string, datos: array<string, mixed>}
     */
    private function procesar(
        EscalonamientoPeriodo $periodo,
        array $bruto,
        bool $escribir,
        ?int $userId,
        string $origen,
        bool $esReporte = false,
        ?string $tipoForzado = null,
    ): array {
        $esReporte = $esReporte || (bool) ($bruto['reporte_remisiones'] ?? false);
        $datos = $this->normalizarFila($bruto, $origen, $esReporte, $tipoForzado);
        if ($datos['numero_cliente'] === '' && $datos['reporte_remisiones']) {
            $coincidencias = $this->coincidenciasNombre((string) $datos['nombre']);
            if (count($coincidencias) === 1) {
                $datos['numero_cliente'] = (string) $coincidencias[0]->numero_cliente;
            } else {
                $datos['coincidencias'] = array_map(
                    fn (Cliente $cliente) => $cliente->numero_cliente.' '.$cliente->nombre,
                    $coincidencias,
                );
            }
        }
        $error = $this->errorEstructural($datos);
        if ($error !== null) {
            if ($escribir) {
                $this->incidencia($periodo, null, null, 'fila_invalida', $error, 'bloquea', $userId);
            }

            return ['resultado' => 'incidencia', 'motivo' => $error, 'datos' => $datos];
        }

        $comparacion = $this->resolverPeriodo->compararConPeriodo($datos['fecha_emision'], $periodo);
        if ($comparacion === null) {
            $motivo = 'La fecha de emisión no es válida.';
            if ($escribir) {
                $this->incidencia($periodo, null, null, 'fila_invalida', $motivo, 'bloquea', $userId);
            }

            return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
        }

        $enrutadoHistorial = $comparacion < 0;
        $enrutadoFuturo = $comparacion > 0;
        $periodoDestino = $periodo;
        $periodoIncidencias = $periodo;

        if ($enrutadoHistorial) {
            $mes = $this->resolverPeriodo->mesDesdeFecha($datos['fecha_emision']);
            $existenteDestino = $this->resolverPeriodo->periodoPorFecha($datos['fecha_emision']);
            if ($existenteDestino?->estaCerradoOficialmente()) {
                $motivo = 'El documento corresponde al período '.$existenteDestino->etiquetaMes().', que ya tiene cierre aplicado.';
                if ($escribir) {
                    $this->incidencia($existenteDestino, null, null, 'documento_tardio', $motivo, 'bloquea', $userId);
                }
                $datos['periodo_destino'] = $existenteDestino->etiquetaMes();

                return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
            }

            if ($escribir) {
                $periodoDestino = $this->asegurarHistorico->asegurar($mes['anio'], $mes['mes']);
            } elseif ($existenteDestino) {
                $periodoDestino = $existenteDestino;
            } else {
                $periodoDestino = new EscalonamientoPeriodo([
                    'anio' => $mes['anio'],
                    'mes' => $mes['mes'],
                    'estado' => EscalonamientoPeriodo::ESTADO_HISTORIAL,
                ]);
            }

            $periodoIncidencias = $periodoDestino->id ? $periodoDestino : $periodo;
            $datos = $this->adjuntarEnrutamiento($datos, $periodo, $periodoDestino);
        } elseif ($enrutadoFuturo) {
            $mes = $this->resolverPeriodo->mesDesdeFecha($datos['fecha_emision']);
            $existenteDestino = $this->resolverPeriodo->periodoPorFecha($datos['fecha_emision']);
            if ($existenteDestino?->estaCerradoOficialmente()) {
                $motivo = 'El documento corresponde al período '.$existenteDestino->etiquetaMes().', que ya tiene cierre aplicado.';
                if ($escribir) {
                    $this->incidencia($existenteDestino, null, null, 'documento_tardio', $motivo, 'bloquea', $userId);
                }
                $datos['periodo_destino'] = $existenteDestino->etiquetaMes();

                return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
            }

            if ($escribir) {
                $periodoDestino = $this->asegurarFuturo->asegurar($mes['anio'], $mes['mes']);
            } elseif ($existenteDestino) {
                $periodoDestino = $existenteDestino;
            } else {
                $periodoDestino = new EscalonamientoPeriodo([
                    'anio' => $mes['anio'],
                    'mes' => $mes['mes'],
                    'estado' => EscalonamientoPeriodo::ESTADO_HISTORIAL,
                ]);
            }

            $periodoIncidencias = $periodoDestino->id ? $periodoDestino : $periodo;
            $datos = $this->adjuntarEnrutamiento($datos, $periodo, $periodoDestino);
            if ($datos['tipo'] === 'remision' && $datos['estado'] === 'activo') {
                $datos['estado'] = 'pendiente_revision';
            }
            $datos['documento_anticipado'] = true;
        } else {
            $datos['periodo_destino'] = $periodo->etiquetaMes();
        }

        $resultado = $this->procesarEnPeriodo(
            $periodoDestino,
            $periodoIncidencias,
            $datos,
            $escribir,
            $userId,
            $origen,
        );

        if ($enrutadoHistorial) {
            return $this->aplicarPrefijoHistorial($resultado, true);
        }

        if ($enrutadoFuturo) {
            if ($escribir && $resultado['resultado'] !== 'incidencia') {
                $this->incidenciaDocumentoAnticipado($periodoIncidencias, $datos, $userId);
            }

            return $this->aplicarPrefijoFuturo($resultado, true);
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{resultado: string, motivo: ?string, datos: array<string, mixed>}
     */
    private function procesarEnPeriodo(
        EscalonamientoPeriodo $periodo,
        EscalonamientoPeriodo $periodoIncidencias,
        array $datos,
        bool $escribir,
        ?int $userId,
        string $origen,
    ): array {
        $cliente = Cliente::query()->where('numero_cliente', $datos['numero_cliente'])->first();
        if ($escribir && ! $periodo->id) {
            throw new InvalidArgumentException('El período destino no está disponible para escribir.');
        }

        if (! $cliente) {
            $motivo = $this->motivoSinCliente($datos);
            $datos['codigo_incidencia'] = 'cliente_no_identificado';
            if ($escribir) {
                $this->incidencia(
                    $periodoIncidencias,
                    null,
                    null,
                    'cliente_no_identificado',
                    $motivo,
                    'bloquea',
                    $userId,
                    $this->contextoClienteNoIdentificado($datos),
                );
            }

            return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
        }

        $clave = $this->registrar->claveDocumento($datos);
        $existente = DocumentoVenta::query()->where('clave_documento', $clave)->first();

        if ($existente && ! $this->documentoEnPeriodo($existente, $periodo)) {
            $motivo = 'El folio '.$datos['folio'].' ya está en otro período. No se modificó.';
            if ($escribir) {
                $this->incidencia($periodoIncidencias, $cliente->id, $existente->id, 'datos_incompatibles', $motivo, 'bloquea', $userId);
            }

            return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
        }

        if ($existente && (int) $existente->cliente_id !== (int) $cliente->id) {
            $motivo = 'El folio '.$datos['folio'].' ya pertenece a otro cliente. No se modificó.';
            if ($escribir) {
                $this->incidencia($periodo, $cliente->id, $existente->id, 'datos_incompatibles', $motivo, 'bloquea', $userId);
            }

            return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
        }

        if ($existente && strtoupper((string) $existente->moneda) !== $datos['moneda']) {
            $motivo = 'El folio '.$datos['folio'].' ya está en '.$existente->moneda.'. No se convirtió la moneda.';
            if ($escribir) {
                $this->incidencia($periodo, $cliente->id, $existente->id, 'moneda_no_soportada', $motivo, 'bloquea', $userId);
            }

            return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
        }

        $efecto = $this->efectoContable($datos);
        $exclusion = null;
        if ($this->excluyePorParticipacion($periodo, $cliente, $datos)) {
            $efecto = '0.00';
            $exclusion = 'La lista del cliente no participa en escalonamiento. El documento se conserva sin sumar al acumulado.';
        }
        if ($existente && $this->esIdentico($existente, $datos, $efecto)) {
            $motivo = 'El documento ya estaba cargado con los mismos datos.';
            if (bccomp($efecto, '0.00', 2) === 0 && $datos['tipo'] === 'remision' && $datos['estado'] === 'activo') {
                $motivo = 'El documento ya estaba cargado. No suma al acumulado porque la lista no participa en escalonamiento.';
            }
            if ($escribir) {
                $this->sincronizarExpedienteRemision($existente, $datos);
            }

            return ['resultado' => 'identico', 'motivo' => $motivo, 'datos' => $datos];
        }

        if ($datos['moneda'] !== 'MXN') {
            $motivo = 'La moneda '.$datos['moneda'].' no se suma. No hay tipo de cambio definido.';
            if ($escribir) {
                $documento = $existente ?: $this->crearDocumento($periodo, $cliente, $datos, $clave, $origen);
                $this->incidencia($periodo, $cliente->id, $documento->id, 'moneda_no_soportada', $motivo, 'bloquea', $userId);
            }

            return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
        }

        if ($datos['tipo'] === 'devolucion') {
            return $this->procesarDevolucion($periodo, $cliente, $datos, $existente, $clave, $origen, $escribir, $userId);
        }

        if ($exclusion !== null) {
            return $this->procesarExclusion(
                $periodo,
                $cliente,
                $datos,
                $existente,
                $clave,
                $origen,
                $escribir,
                $userId,
                $exclusion,
            );
        }

        if ($existente) {
            if (! $escribir) {
                return [
                    'resultado' => 'revision',
                    'motivo' => $this->motivoRevisionPrevisualizacion($existente, $datos, $efecto),
                    'datos' => $datos,
                ];
            }

            try {
                $this->registrar->aplicarDiferencia($existente, $datos['total'], $efecto, $datos['estado'], $userId, $datos);
                $this->resolverIncidenciasExclusionTrasAplicar($periodo, $cliente, $existente, $efecto);
            } catch (AcumuladoNegativoException) {
                $motivo = 'La revisión del folio '.$datos['folio'].' dejaría el acumulado negativo. No se aplicó.';
                $this->incidencia($periodo, $cliente->id, $existente->id, 'acumulado_negativo', $motivo, 'bloquea', $userId);

                return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
            }

            return ['resultado' => 'revision', 'motivo' => 'Se actualizó el documento con el efecto diferencial.', 'datos' => $datos];
        }

        if (! $escribir) {
            $motivo = $datos['estado'] === 'cancelado'
                ? 'Remisión cancelada. No suma al acumulado.'
                : null;

            return ['resultado' => 'alta', 'motivo' => $motivo, 'datos' => $datos];
        }

        if (bccomp($efecto, '0.00', 2) === 0) {
            $this->crearDocumento($periodo, $cliente, $datos, $clave, $origen);

            return [
                'resultado' => 'alta',
                'motivo' => 'Remisión cancelada. No suma al acumulado.',
                'datos' => $datos,
            ];
        }

        $this->registrar->registrar([
            'periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => $datos['tipo'],
            'folio' => $datos['folio'],
            'serie' => $datos['serie'],
            'sucursal' => $datos['sucursal'],
            'moneda' => $datos['moneda'],
            'total' => $datos['total'],
            'efecto' => $efecto,
            'fecha_emision' => $datos['fecha_emision'],
            'origen' => $origen,
            'estado' => $datos['estado'],
            'operacion' => 'remision',
            'datos_fuente' => $datos['datos_fuente'],
            'reporte_remisiones' => $datos['reporte_remisiones'] ?? false,
            'fila_bruta' => $datos['fila_bruta'] ?? null,
        ]);

        return ['resultado' => 'alta', 'motivo' => null, 'datos' => $datos];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{resultado: string, motivo: ?string, datos: array<string, mixed>}
     */
    private function procesarDevolucion(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        array $datos,
        ?DocumentoVenta $existente,
        string $clave,
        string $origen,
        bool $escribir,
        ?int $userId,
    ): array {
        if ($datos['estado'] === 'cancelado') {
            return $this->procesarDevolucionCancelada($periodo, $cliente, $datos, $existente, $clave, $origen, $escribir, $userId);
        }

        if ($existente && $this->tieneAplicacionActiva($existente)) {
            if (bccomp($this->dinero($existente->total), $datos['total'], 2) === 0) {
                return [
                    'resultado' => 'identico',
                    'motivo' => 'La devolución ya está vinculada. El acumulado no cambió.',
                    'datos' => $datos,
                ];
            }

            $motivo = 'El total de la devolución '.$datos['folio'].' cambió después del vínculo. No se modificó el descuento.';
            if ($escribir) {
                $this->incidencia($periodo, $cliente->id, $existente->id, 'datos_incompatibles', $motivo, 'bloquea', $userId);
            }

            return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
        }

        $motivo = 'Devolución conservada sin descuento. Falta confirmar la compra nueva que la absorbe.';
        if ($escribir) {
            $documento = $existente
                ? $this->revisarSinEfecto($existente, $datos, $userId)
                : $this->crearDocumento($periodo, $cliente, $datos, $clave, $origen);
            $this->incidencia($periodo, $cliente->id, $documento->id, 'pendiente_vinculacion', $motivo, 'aviso', $userId);
        }

        return ['resultado' => 'pendiente', 'motivo' => $motivo, 'datos' => $datos];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{resultado: string, motivo: ?string, datos: array<string, mixed>}
     */
    private function procesarDevolucionCancelada(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        array $datos,
        ?DocumentoVenta $existente,
        string $clave,
        string $origen,
        bool $escribir,
        ?int $userId,
    ): array {
        $aplicacion = $existente
            ? EscalonamientoAplicacionDevolucion::query()
                ->where('documento_devolucion_id', $existente->id)
                ->where('estado', EscalonamientoAplicacionDevolucion::ESTADO_ACTIVA)
                ->first()
            : null;

        if ($aplicacion) {
            $motivo = 'La cancelación revierte el descuento de la devolución una sola vez.';
            if (! $escribir) {
                return ['resultado' => 'revision', 'motivo' => $motivo, 'datos' => $datos];
            }

            $this->revertirAplicacion->revertir($aplicacion, $userId ? User::query()->find($userId) : null, 'cancelado');

            return ['resultado' => 'revision', 'motivo' => $motivo, 'datos' => $datos];
        }

        if ($existente && $existente->estado === 'cancelado' && bccomp($this->dinero($existente->total), $datos['total'], 2) === 0) {
            return ['resultado' => 'identico', 'motivo' => 'La devolución cancelada ya estaba registrada.', 'datos' => $datos];
        }

        $motivo = 'Devolución cancelada. No descuenta el acumulado.';
        if ($escribir) {
            if ($existente) {
                $this->revisarSinEfecto($existente, $datos, $userId);
            } else {
                $this->crearDocumento($periodo, $cliente, $datos, $clave, $origen);
            }
        }

        return ['resultado' => $existente ? 'revision' : 'alta', 'motivo' => $motivo, 'datos' => $datos];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{resultado: string, motivo: ?string, datos: array<string, mixed>}
     */
    private function procesarExclusion(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        array $datos,
        ?DocumentoVenta $existente,
        string $clave,
        string $origen,
        bool $escribir,
        ?int $userId,
        string $exclusion,
    ): array {
        if (! $escribir) {
            return ['resultado' => 'excluido', 'motivo' => $exclusion, 'datos' => $datos];
        }

        if ($existente) {
            try {
                $this->registrar->aplicarDiferencia($existente, $datos['total'], '0.00', $datos['estado'], $userId, $datos);
            } catch (AcumuladoNegativoException) {
                $motivo = 'La revisión del folio '.$datos['folio'].' dejaría el acumulado negativo. No se aplicó.';
                $this->incidencia($periodo, $cliente->id, $existente->id, 'acumulado_negativo', $motivo, 'bloquea', $userId);

                return ['resultado' => 'incidencia', 'motivo' => $motivo, 'datos' => $datos];
            }
            $documento = $existente;
        } else {
            $documento = $this->crearDocumento($periodo, $cliente, $datos, $clave, $origen);
        }

        $this->registrarExclusionInformativa($periodo, $cliente->id, $documento->id, $exclusion, $userId);

        return ['resultado' => 'excluido', 'motivo' => $exclusion, 'datos' => $datos];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function excluyePorParticipacion(EscalonamientoPeriodo $periodo, Cliente $cliente, array $datos): bool
    {
        if ($datos['tipo'] !== 'remision' || $datos['moneda'] !== 'MXN' || $datos['estado'] !== 'activo') {
            return false;
        }

        return ! $this->participacion->clienteParticipa($periodo, $cliente);
    }

    private function tieneAplicacionActiva(DocumentoVenta $documento): bool
    {
        return EscalonamientoAplicacionDevolucion::query()
            ->where('documento_devolucion_id', $documento->id)
            ->where('estado', EscalonamientoAplicacionDevolucion::ESTADO_ACTIVA)
            ->exists();
    }

    /**
     * @param  array<string, int>  $conteo
     */
    private function mensaje(array $conteo): string
    {
        $historial = (int) ($conteo['historial_alta'] ?? 0)
            + (int) ($conteo['historial_identico'] ?? 0)
            + (int) ($conteo['historial_revision'] ?? 0)
            + (int) ($conteo['historial_pendiente'] ?? 0)
            + (int) ($conteo['historial_excluido'] ?? 0);

        $futuro = (int) ($conteo['futuro_alta'] ?? 0)
            + (int) ($conteo['futuro_identico'] ?? 0)
            + (int) ($conteo['futuro_revision'] ?? 0)
            + (int) ($conteo['futuro_pendiente'] ?? 0)
            + (int) ($conteo['futuro_excluido'] ?? 0);

        return sprintf(
            'Carga confirmada: %d altas, %d sin cambio, %d revisiones, %d pendientes de vinculación, %d filas de historial, %d de período futuro y %d incidencias. Las devoluciones no descuentan hasta confirmar el vínculo con la compra nueva.',
            (int) ($conteo['alta'] ?? 0),
            (int) ($conteo['identico'] ?? 0),
            (int) ($conteo['revision'] ?? 0),
            (int) ($conteo['pendiente'] ?? 0),
            $historial,
            $futuro,
            (int) ($conteo['incidencia'] ?? 0),
        );
    }

    /**
     * @return list<array{numero_fila: int, datos: array<string, mixed>}>
     */
    private function leer(UploadedFile $archivo): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            throw new InvalidArgumentException('Usa un archivo CSV o Excel.');
        }

        $path = $archivo->getRealPath();
        if ($path === false) {
            throw new InvalidArgumentException('No se pudo leer el archivo.');
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->leerExcel($path);
        }

        return $this->leerCsv($path);
    }

    /**
     * @return list<array{numero_fila: int, datos: array<string, mixed>}>
     */
    private function leerCsv(string $path): array
    {
        $file = fopen($path, 'r');
        if ($file === false) {
            throw new InvalidArgumentException('No se pudo abrir el CSV.');
        }

        $primera = fgets($file);
        if ($primera === false) {
            fclose($file);

            return [];
        }
        $primera = preg_replace('/^\xEF\xBB\xBF/', '', $primera) ?: '';
        $delimitador = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
        rewind($file);

        $crudas = [];
        while (($row = fgetcsv($file, 0, $delimitador)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($row[0] ?? '')) ?: ($row[0] ?? '');
            $crudas[] = $row;
        }
        fclose($file);

        return $this->construirFilasDesdeCrudas($crudas);
    }

    /**
     * @return list<array{numero_fila: int, datos: array<string, mixed>}>
     */
    private function leerExcel(string $path): array
    {
        $crudas = [];
        (new FastExcel)->withoutHeaders()->import($path, function ($row) use (&$crudas): void {
            $crudas[] = array_values(is_array($row) ? $row : (array) $row);
        });

        return $this->construirFilasDesdeCrudas($crudas);
    }

    /**
     * @param  list<list<mixed>>  $crudas
     * @return list<array{numero_fila: int, datos: array<string, mixed>}>
     */
    private function construirFilasDesdeCrudas(array $crudas): array
    {
        if ($crudas === []) {
            return [];
        }

        $indiceCabecera = null;
        $limite = min(count($crudas), 8);
        for ($i = 0; $i < $limite; $i++) {
            if ($this->filaEsCabeceraReporte($crudas[$i]) || $this->filaEsCabeceraEstandar($crudas[$i])) {
                $indiceCabecera = $i;
                break;
            }
        }

        if ($indiceCabecera === null) {
            throw new InvalidArgumentException('No se encontró una fila de encabezados con Folio, Fecha, Cliente y Total.');
        }

        $cabeceras = $crudas[$indiceCabecera];
        $filas = [];
        $numero = $indiceCabecera + 1;
        for ($i = $indiceCabecera + 1, $total = count($crudas); $i < $total; $i++) {
            $numero++;
            $row = $crudas[$i];
            if ($this->filaVacia($row)) {
                continue;
            }
            $datos = [];
            foreach ($cabeceras as $indice => $cabecera) {
                $nombre = trim((string) $cabecera);
                if ($nombre === '') {
                    continue;
                }
                $datos[$nombre] = $row[$indice] ?? null;
            }
            if ($datos === []) {
                continue;
            }
            $filas[] = ['numero_fila' => $numero, 'datos' => $datos];
        }

        return $filas;
    }

    /**
     * @param  list<mixed>  $celdas
     */
    private function filaEsCabeceraReporte(array $celdas): bool
    {
        $claves = [];
        foreach ($celdas as $valor) {
            if ($valor === null || trim((string) $valor) === '') {
                continue;
            }
            $claves[] = $this->clave((string) $valor);
        }

        foreach (['folio', 'fecha', 'cliente', 'total'] as $requerida) {
            if (! in_array($requerida, $claves, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $celdas
     */
    private function filaEsCabeceraEstandar(array $celdas): bool
    {
        $claves = [];
        foreach ($celdas as $valor) {
            if ($valor === null || trim((string) $valor) === '') {
                continue;
            }
            $claves[] = $this->clave((string) $valor);
        }

        foreach (['tipo', 'folio', 'fecha', 'total'] as $requerida) {
            if (! in_array($requerida, $claves, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $bruto
     * @return array<string, mixed>
     */
    private function normalizarFila(array $bruto, string $origen, bool $esReporte = false, ?string $tipoForzado = null): array
    {
        $tipo = $this->clave($this->valor($bruto, ['tipo', 'tipo_documento']));
        if (in_array($tipo, ['remision', 'remisiones', 'venta'], true)) {
            $tipo = 'remision';
        } elseif (in_array($tipo, ['devolucion', 'devoluciones'], true)) {
            $tipo = 'devolucion';
        } else {
            $tipo = '';
        }

        if ($tipo === '' && $tipoForzado !== null && $tipoForzado !== '') {
            $tipo = $tipoForzado === 'devolucion' ? 'devolucion' : 'remision';
        } elseif ($tipo === '' && $esReporte) {
            $tipo = 'remision';
        }

        $moneda = strtoupper($this->valor($bruto, ['moneda']));
        if ($moneda === '') {
            $moneda = 'MXN';
        }

        $estadoRaw = $this->clave($this->valor($bruto, ['estado', 'status', 'estatus']));
        $cancelado = in_array($estadoRaw, ['cancelada', 'cancelado', 'cancel'], true);

        if ($moneda !== 'MXN') {
            $estado = 'excluido';
        } elseif ($tipo === 'devolucion' && ! $cancelado) {
            $estado = 'pendiente_de_vinculacion';
        } elseif ($cancelado) {
            $estado = 'cancelado';
        } else {
            $estado = 'activo';
        }

        $numero = $this->texto($this->valor($bruto, ['numero_cliente', 'numero', 'num_cliente', 'no_cliente']));
        $nombre = $this->valor($bruto, ['nombre', 'nombre_cliente', 'razon_social']);
        if ($esReporte) {
            $nombreCliente = $this->valor($bruto, ['cliente']);
            if ($nombreCliente !== '') {
                $nombre = $nombreCliente;
            }
        } elseif ($numero === '') {
            $clienteColumna = $this->valor($bruto, ['cliente']);
            if ($clienteColumna !== '' && ! preg_match('/\s/u', $clienteColumna)) {
                $numero = $this->texto($clienteColumna);
            } elseif ($nombre === '' && $clienteColumna !== '') {
                $nombre = $clienteColumna;
            }
        } elseif ($nombre === '') {
            $clienteColumna = $this->valor($bruto, ['cliente']);
            if ($clienteColumna !== '' && preg_match('/\s/u', $clienteColumna)) {
                $nombre = $clienteColumna;
            }
        }

        $fuente = null;
        if (is_array($bruto['datos_fuente'] ?? null)) {
            $fuente = $bruto['datos_fuente'] === [] ? null : $bruto['datos_fuente'];
        } elseif ($esReporte) {
            $construida = $this->mapearExpediente->datosFuenteDesdeBruto($bruto);
            $fuente = $construida === [] ? null : $construida;
        }

        return [
            'tipo' => $tipo,
            'folio' => $this->texto($this->valor($bruto, ['folio', 'folio_documento'])),
            'serie' => $this->vacio($this->texto($this->valor($bruto, ['serie']))),
            'sucursal' => $this->vacio($this->texto($this->valor($bruto, $esReporte ? ['sucursal'] : ['sucursal', 'almacen']))),
            'numero_cliente' => $numero,
            'nombre' => trim($nombre),
            'moneda' => $moneda,
            'total' => $this->total($this->valorMixto($bruto, ['total', 'importe', 'total_documento'])),
            'fecha_emision' => $this->fecha($this->valorMixto($bruto, ['fecha_emision', 'fecha', 'fecha_documento'])),
            'estado' => $estado,
            'remision_original' => $this->vacio($this->texto($this->valor($bruto, ['remision_original', 'folio_origen', 'remision']))),
            'origen' => $origen,
            'reporte_remisiones' => $esReporte,
            'datos_fuente' => $fuente,
            'fila_bruta' => $esReporte ? $bruto : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function errorEstructural(array $datos): ?string
    {
        if (! in_array($datos['tipo'], ['remision', 'devolucion'], true)) {
            return 'El tipo debe ser remisión o devolución.';
        }
        if ($datos['folio'] === '') {
            return 'Falta el folio.';
        }
        if ($datos['total'] === null || bccomp($datos['total'], '0.00', 2) <= 0) {
            return 'El total debe ser mayor que cero.';
        }
        if ($datos['fecha_emision'] === null) {
            return 'La fecha no es válida.';
        }
        if (strlen($datos['moneda']) > 8) {
            return 'La moneda no es válida.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function motivoSinCliente(array $datos): string
    {
        if (($datos['reporte_remisiones'] ?? false) && $datos['numero_cliente'] === '') {
            return $this->motivoNombreReporte($datos);
        }

        $numero = $datos['numero_cliente'] !== ''
            ? 'No hay un cliente con el número '.$datos['numero_cliente'].'.'
            : 'Falta el número de cliente.';
        $candidatos = $this->candidatos($datos['nombre'] ?? '');
        if ($candidatos === []) {
            return $numero.' El nombre no asigna el documento.';
        }

        $lista = implode(', ', array_map(
            fn (Cliente $cliente) => $cliente->numero_cliente.' '.$cliente->nombre,
            $candidatos,
        ));

        return $numero.' Candidatos por nombre: '.$lista.'. El nombre no asigna el documento.';
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function motivoNombreReporte(array $datos): string
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $coincidencias = is_array($datos['coincidencias'] ?? null) ? $datos['coincidencias'] : [];
        if (count($coincidencias) > 1) {
            return 'El nombre '.$nombre.' coincide con varios clientes: '.implode(', ', $coincidencias).'. El nombre no asigna el documento.';
        }

        $texto = $nombre !== ''
            ? 'No hay un cliente con el nombre '.$nombre.'.'
            : 'Falta el nombre del cliente.';

        return $texto.' El nombre no asigna el documento.';
    }

    /**
     * @return list<Cliente>
     */
    private function coincidenciasNombre(string $nombre): array
    {
        $clave = $this->normalizarNombre($nombre);
        if ($clave === '') {
            return [];
        }

        $this->cargarIndice();

        return array_values($this->indiceNombres[$clave] ?? []);
    }

    private function cargarIndice(): void
    {
        if ($this->indiceCargado) {
            return;
        }

        $this->indiceCargado = true;
        $this->indiceNombres = [];
        Cliente::query()
            ->select(['id', 'numero_cliente', 'nombre', 'nombre_razon_social'])
            ->orderBy('id')
            ->chunk(1000, function ($clientes): void {
                foreach ($clientes as $cliente) {
                    foreach ([$cliente->nombre, $cliente->nombre_razon_social] as $campo) {
                        $clave = $this->normalizarNombre((string) $campo);
                        if ($clave === '') {
                            continue;
                        }
                        $this->indiceNombres[$clave][$cliente->id] = $cliente;
                    }
                }
            });
    }

    private function reiniciarIndice(): void
    {
        $this->indiceCargado = false;
        $this->indiceNombres = [];
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return '';
        }

        $nombre = mb_strtoupper($nombre, 'UTF-8');
        $nombre = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
            ['A', 'E', 'I', 'O', 'U', 'U', 'N'],
            $nombre,
        );
        $nombre = preg_replace('/\s+/u', ' ', $nombre) ?? $nombre;

        return trim($nombre);
    }

    /**
     * @param  list<array{numero_fila: int, datos: array<string, mixed>}>  $filas
     */
    private function esReporteRemisiones(array $filas): bool
    {
        $datos = $filas[0]['datos'] ?? null;
        if (! is_array($datos)) {
            return false;
        }

        $claves = [];
        foreach (array_keys($datos) as $clave) {
            $claves[] = $this->clave((string) $clave);
        }
        if (in_array('tipo', $claves, true) || in_array('tipo_documento', $claves, true)) {
            return false;
        }
        foreach (['folio', 'fecha', 'cliente', 'total'] as $requerida) {
            if (! in_array($requerida, $claves, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $bruto
     * @return array<string, string>
     */
    /**
     * @return list<Cliente>
     */
    private function candidatos(string $nombre): array
    {
        $nombre = trim($nombre);
        if (mb_strlen($nombre) < 3) {
            return [];
        }

        $escapado = addcslashes($nombre, '%_\\');

        return Cliente::query()
            ->where('nombre', 'like', '%'.$escapado.'%')
            ->orderBy('numero_cliente')
            ->limit(5)
            ->get(['id', 'numero_cliente', 'nombre'])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function efectoContable(array $datos): string
    {
        if ($datos['moneda'] !== 'MXN' || $datos['tipo'] !== 'remision' || $datos['estado'] !== 'activo') {
            return '0.00';
        }

        return $datos['total'];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function esIdentico(DocumentoVenta $documento, array $datos, string $efecto): bool
    {
        $efectoActual = $documento->movimiento ? $this->dinero($documento->movimiento->efecto) : '0.00';

        return bccomp($this->dinero($documento->total), $datos['total'], 2) === 0
            && bccomp($efectoActual, $efecto, 2) === 0
            && $documento->estado === $datos['estado']
            && strtoupper((string) $documento->moneda) === $datos['moneda'];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearDocumento(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        array $datos,
        string $clave,
        string $origen,
    ): DocumentoVenta {
        $documento = DocumentoVenta::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'tipo' => $datos['tipo'],
            'folio' => $datos['folio'],
            'serie' => $datos['serie'],
            'sucursal' => $datos['sucursal'],
            'moneda' => $datos['moneda'],
            'total' => $datos['total'],
            'estado' => $datos['estado'],
            'fecha_emision' => $datos['fecha_emision'],
            'origen' => $origen,
            'clave_documento' => $clave,
            'remision_original' => $datos['remision_original'],
            'datos_fuente' => $datos['datos_fuente'] ?? null,
        ]);
        $this->sincronizarExpedienteRemision($documento, $datos);

        return $documento;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function revisarSinEfecto(DocumentoVenta $documento, array $datos, ?int $userId): DocumentoVenta
    {
        $efectoActual = $documento->movimiento ? $this->dinero($documento->movimiento->efecto) : '0.00';
        $totalAnterior = $this->dinero($documento->total);
        $documento->total = $datos['total'];
        $documento->estado = $datos['estado'];
        $documento->remision_original = $datos['remision_original'];
        if (array_key_exists('datos_fuente', $datos)) {
            $documento->datos_fuente = $datos['datos_fuente'];
        }
        $documento->save();
        $this->sincronizarExpedienteRemision($documento, $datos);

        if (bccomp($totalAnterior, $datos['total'], 2) !== 0 || $documento->wasChanged('estado')) {
            DocumentoVentaRevision::create([
                'documento_venta_id' => $documento->id,
                'total_anterior' => $totalAnterior,
                'total_nuevo' => $datos['total'],
                'efecto_anterior' => $efectoActual,
                'efecto_nuevo' => $efectoActual,
                'user_id' => $userId,
            ]);
        }

        return $documento;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function registrarExclusionInformativa(
        EscalonamientoPeriodo $periodo,
        int $clienteId,
        int $documentoId,
        string $motivo,
        ?int $userId,
    ): void {
        $existe = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('documento_venta_id', $documentoId)
            ->where('codigo', 'exclusion_lealtad')
            ->exists();
        if ($existe) {
            return;
        }

        EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $clienteId,
            'documento_venta_id' => $documentoId,
            'gravedad' => 'aviso',
            'codigo' => 'exclusion_lealtad',
            'motivo' => $motivo,
            'estado' => 'resuelta',
            'resolucion' => 'Registro informativo: el documento se conserva sin sumar al acumulado.',
            'resuelto_en' => now(),
            'user_id' => $userId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function contextoClienteNoIdentificado(array $datos): array
    {
        $numero = (string) ($datos['numero_cliente'] ?? '');
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $clave = $numero !== '' ? 'n:'.$numero : 'nombre:'.mb_strtolower($nombre);
        $candidatos = array_map(fn (Cliente $cliente) => [
            'id' => $cliente->id,
            'numero_cliente' => $cliente->numero_cliente,
            'nombre' => $cliente->nombre,
        ], $this->candidatos($nombre));

        return [
            'clave' => $clave,
            'numero_cliente' => $numero,
            'nombre' => $nombre,
            'candidatos' => $candidatos,
            'documentos' => [$datos],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $contexto
     */
    private function incidencia(
        EscalonamientoPeriodo $periodo,
        ?int $clienteId,
        ?int $documentoId,
        string $codigo,
        string $motivo,
        string $gravedad,
        ?int $userId,
        ?array $contexto = null,
    ): void {
        if ($codigo === 'cliente_no_identificado' && is_array($contexto)) {
            $this->acumularClienteNoIdentificado($periodo, $motivo, $gravedad, $userId, $contexto);

            return;
        }

        $existe = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('codigo', $codigo)
            ->where('motivo', $motivo)
            ->where('estado', 'abierta')
            ->exists();
        if ($existe) {
            return;
        }

        EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $clienteId,
            'documento_venta_id' => $documentoId,
            'gravedad' => $gravedad,
            'codigo' => $codigo,
            'motivo' => $motivo,
            'estado' => 'abierta',
            'user_id' => $userId,
            'contexto' => $contexto,
        ]);
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    private function acumularClienteNoIdentificado(
        EscalonamientoPeriodo $periodo,
        string $motivo,
        string $gravedad,
        ?int $userId,
        array $contexto,
    ): void {
        $clave = (string) ($contexto['clave'] ?? '');
        $existente = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('codigo', 'cliente_no_identificado')
            ->where('estado', 'abierta')
            ->where('contexto->clave', $clave)
            ->first();

        $documentos = is_array($existente?->contexto['documentos'] ?? null)
            ? $existente->contexto['documentos']
            : [];
        foreach ($contexto['documentos'] ?? [] as $documento) {
            if (! is_array($documento)) {
                continue;
            }
            $ya = false;
            foreach ($documentos as $previo) {
                if (($previo['folio'] ?? '') === ($documento['folio'] ?? '')
                    && ($previo['tipo'] ?? '') === ($documento['tipo'] ?? '')) {
                    $ya = true;
                    break;
                }
            }
            if (! $ya) {
                $documentos[] = $documento;
            }
        }

        $contexto['documentos'] = $documentos;
        $motivo = $motivo.' Documentos pendientes: '.count($documentos).'.';

        if ($existente) {
            $existente->motivo = $motivo;
            $existente->contexto = $contexto;
            $existente->save();

            return;
        }

        EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => null,
            'documento_venta_id' => null,
            'gravedad' => $gravedad,
            'codigo' => 'cliente_no_identificado',
            'motivo' => $motivo,
            'estado' => 'abierta',
            'user_id' => $userId,
            'contexto' => $contexto,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{resultado: string, motivo: ?string, datos: array<string, mixed>}
     */
    public function registrarFilaEnPeriodo(EscalonamientoPeriodo $periodo, array $datos, ?User $usuario): array
    {
        $this->exigirAbierto($periodo);
        $this->reiniciarIndice();

        return $this->procesar(
            $periodo,
            $datos,
            true,
            $usuario?->id,
            (string) ($datos['origen'] ?? 'resolucion'),
            (bool) ($datos['reporte_remisiones'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{resultado: string, motivo: ?string, datos: array<string, mixed>}
     */
    public function reinterpretarFila(EscalonamientoPeriodo $periodo, array $datos): array
    {
        $this->reiniciarIndice();

        return $this->procesar(
            $periodo,
            $datos,
            false,
            null,
            (string) ($datos['origen'] ?? 'archivo'),
            (bool) ($datos['reporte_remisiones'] ?? false),
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function adjuntarEnrutamiento(
        array $datos,
        EscalonamientoPeriodo $importacion,
        EscalonamientoPeriodo $destino,
    ): array {
        $fuente = is_array($datos['datos_fuente'] ?? null) ? $datos['datos_fuente'] : [];
        $fuente['periodo_importacion_id'] = $importacion->id;
        $fuente['periodo_destino_etiqueta'] = $destino->etiquetaMes();
        if ($destino->id) {
            $fuente['periodo_destino_id'] = $destino->id;
        }
        $datos['datos_fuente'] = $fuente;
        $datos['periodo_destino'] = $destino->etiquetaMes();

        return $datos;
    }

    /**
     * @param  array{resultado: string, motivo: ?string, datos: array<string, mixed>}  $resultado
     * @return array{resultado: string, motivo: ?string, datos: array<string, mixed>}
     */
    private function aplicarPrefijoHistorial(array $resultado, bool $enrutado): array
    {
        if (! $enrutado) {
            return $resultado;
        }

        $map = [
            'alta' => 'historial_alta',
            'identico' => 'historial_identico',
            'revision' => 'historial_revision',
            'pendiente' => 'historial_pendiente',
            'excluido' => 'historial_excluido',
        ];

        $clave = $resultado['resultado'];
        if (! isset($map[$clave])) {
            return $resultado;
        }

        $resultado['resultado'] = $map[$clave];
        if ($clave === 'identico') {
            $etiqueta = (string) ($resultado['datos']['periodo_destino'] ?? 'histórico');
            $resultado['motivo'] = 'La remisión ya estaba registrada en el período '.$etiqueta.'.';
        }

        return $resultado;
    }

    /**
     * @param  array{resultado: string, motivo: ?string, datos: array<string, mixed>}  $resultado
     * @return array{resultado: string, motivo: ?string, datos: array<string, mixed>}
     */
    private function aplicarPrefijoFuturo(array $resultado, bool $enrutado): array
    {
        if (! $enrutado) {
            return $resultado;
        }

        $map = [
            'alta' => 'futuro_alta',
            'identico' => 'futuro_identico',
            'revision' => 'futuro_revision',
            'pendiente' => 'futuro_pendiente',
            'excluido' => 'futuro_excluido',
        ];

        $clave = $resultado['resultado'];
        if (! isset($map[$clave])) {
            return $resultado;
        }

        $resultado['resultado'] = $map[$clave];
        $etiqueta = (string) ($resultado['datos']['periodo_destino'] ?? 'futuro');
        if ($clave === 'alta' || $clave === 'pendiente') {
            $resultado['motivo'] = 'Documento registrado en '.$etiqueta.' pendiente de revisión administrativa.';
        } elseif ($clave === 'identico') {
            $resultado['motivo'] = 'El documento ya estaba registrado en el período '.$etiqueta.'.';
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function incidenciaDocumentoAnticipado(EscalonamientoPeriodo $periodo, array $datos, ?int $userId): void
    {
        $cliente = Cliente::query()->where('numero_cliente', $datos['numero_cliente'])->first();
        if (! $cliente) {
            return;
        }

        $clave = $this->registrar->claveDocumento($datos);
        $documento = DocumentoVenta::query()->where('clave_documento', $clave)->first();
        $destino = (string) ($datos['periodo_destino'] ?? $periodo->etiquetaMes());
        $motivo = 'Documento del período '.$destino.' cargado por anticipado. Revisión administrativa pendiente antes de sumar al acumulado.';
        $this->incidencia($periodo, $cliente->id, $documento?->id, 'documento_anticipado', $motivo, 'advertencia', $userId);
    }

    private function documentoEnPeriodo(DocumentoVenta $documento, EscalonamientoPeriodo $periodo): bool
    {
        if ($periodo->id) {
            return (int) $documento->escalonamiento_periodo_id === (int) $periodo->id;
        }

        $docPeriodo = $documento->relationLoaded('periodo')
            ? $documento->periodo
            : EscalonamientoPeriodo::query()->find($documento->escalonamiento_periodo_id);

        if (! $docPeriodo) {
            return false;
        }

        return (int) $docPeriodo->anio === (int) $periodo->anio
            && (int) $docPeriodo->mes === (int) $periodo->mes;
    }

    private function fechaEnPeriodo(?string $fecha, EscalonamientoPeriodo $periodo): bool
    {
        return $this->resolverPeriodo->compararConPeriodo($fecha, $periodo) === 0;
    }

    private function exigirAbierto(EscalonamientoPeriodo $periodo): void
    {
        if (! $periodo->estaAbierto()) {
            throw new PeriodoNoAbiertoException('El período no está abierto.');
        }
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function filaVacia(array $row): bool
    {
        foreach ($row as $valor) {
            if ($valor !== null && trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  list<string>  $alias
     */
    private function valor(array $fila, array $alias): string
    {
        return $this->texto($this->valorMixto($fila, $alias));
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  list<string>  $alias
     */
    private function valorMixto(array $fila, array $alias): mixed
    {
        foreach ($fila as $clave => $valor) {
            if (in_array($this->clave((string) $clave), $alias, true)) {
                return $valor;
            }
        }

        return null;
    }

    private function clave(string $nombre): string
    {
        $nombre = preg_replace('/^\xEF\xBB\xBF/', '', $nombre) ?: '';
        $nombre = mb_strtolower(trim($nombre));
        $nombre = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $nombre,
        );
        $nombre = preg_replace('/[^a-z0-9]+/', '_', $nombre) ?: '';

        return trim($nombre, '_');
    }

    private function texto(mixed $valor): string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        if (is_int($valor)) {
            return (string) $valor;
        }
        if (is_float($valor)) {
            if (floor($valor) == $valor) {
                return sprintf('%.0f', $valor);
            }

            return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
        }

        return trim((string) $valor);
    }

    private function total(mixed $valor): ?string
    {
        if ($valor instanceof DateTimeInterface || $valor === null) {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return number_format((float) $valor, 2, '.', '');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $limpio = preg_replace('/[^\d,.\-]/', '', $texto) ?: '';
        if ($limpio === '' || $limpio === '-') {
            return null;
        }

        if (str_contains($limpio, ',') && str_contains($limpio, '.')) {
            if (strrpos($limpio, ',') > strrpos($limpio, '.')) {
                $limpio = str_replace('.', '', $limpio);
                $limpio = str_replace(',', '.', $limpio);
            } else {
                $limpio = str_replace(',', '', $limpio);
            }
        } elseif (str_contains($limpio, ',')) {
            $partes = explode(',', $limpio);
            $limpio = strlen((string) end($partes)) === 2
                ? str_replace(',', '.', $limpio)
                : str_replace(',', '', $limpio);
        }

        if (! is_numeric($limpio)) {
            return null;
        }

        return bcadd($limpio, '0', 2);
    }

    private function fecha(mixed $valor): ?string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $formato) {
            $fecha = DateTimeImmutable::createFromFormat('!'.$formato, $texto);
            $errores = DateTimeImmutable::getLastErrors();
            if ($fecha instanceof DateTimeImmutable && ($errores === false || ($errores['warning_count'] === 0 && $errores['error_count'] === 0))) {
                return $fecha->format('Y-m-d');
            }
        }

        return null;
    }

    private function vacio(string $valor): ?string
    {
        return $valor === '' ? null : $valor;
    }

    private function dinero(float|int|string $valor): string
    {
        return bcadd((string) $valor, '0', 2);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function sincronizarExpedienteRemision(DocumentoVenta $documento, array $datos): void
    {
        $this->sincronizarExpediente->desdeDatosImportacion($documento, $datos);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function motivoRevisionPrevisualizacion(DocumentoVenta $existente, array $datos, string $efecto): string
    {
        $efectoActual = $existente->movimiento ? $this->dinero($existente->movimiento->efecto) : '0.00';
        $mismoTotal = bccomp($this->dinero($existente->total), $datos['total'], 2) === 0
            && $existente->estado === $datos['estado'];

        if ($mismoTotal && bccomp($efectoActual, $efecto, 2) !== 0) {
            if (bccomp($efecto, '0.00', 2) > 0 && bccomp($efectoActual, '0.00', 2) === 0) {
                return 'El documento ya estaba registrado sin acumulado. Se aplicará al confirmar.';
            }
            if (bccomp($efecto, '0.00', 2) === 0 && bccomp($efectoActual, '0.00', 2) > 0) {
                return 'La lista ya no participa: se revertirá el acumulado de este folio al confirmar.';
            }
        }

        return 'El total o el estado cambiaron. Se aplicará la diferencia, sin un segundo alta.';
    }

    private function resolverIncidenciasExclusionTrasAplicar(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        DocumentoVenta $documento,
        string $efecto,
    ): void {
        if (bccomp($efecto, '0.00', 2) <= 0) {
            return;
        }

        EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('cliente_id', $cliente->id)
            ->where('documento_venta_id', $documento->id)
            ->where('codigo', 'exclusion_lealtad')
            ->where('estado', 'abierta')
            ->update(['estado' => 'resuelta']);
    }
}
