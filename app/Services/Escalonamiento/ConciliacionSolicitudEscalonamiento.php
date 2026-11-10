<?php

namespace App\Services\Escalonamiento;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\SolicitudTag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConciliacionSolicitudEscalonamiento
{
    public function __construct(private EscalonamientoAutoridad $autoridad) {}
    /**
     * @return list<array<string, mixed>>
     */
    public function listarSolicitudesPeriodo(EscalonamientoPeriodo $periodo): array
    {
        $inicio = Carbon::create($periodo->anio, $periodo->mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();

        $solicitudes = SolicitudTag::query()
            ->with(['cliente:id,numero_cliente,nombre', 'estado:id,nombre'])
            ->where('pago_confirmado', true)
            ->whereNotNull('cliente_id')
            ->where(function ($q) use ($inicio, $fin) {
                $q->whereBetween('fecha_operacion', [$inicio->toDateString(), $fin->toDateString()])
                    ->orWhereBetween('updated_at', [$inicio, $fin]);
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $conciliaciones = EscalonamientoConciliacionSolicitud::query()
            ->with('documento:id,folio')
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->whereIn('solicitud_tag_id', $solicitudes->pluck('id'))
            ->get()
            ->groupBy('solicitud_tag_id');

        return $solicitudes->map(function (SolicitudTag $solicitud) use ($periodo, $conciliaciones) {
            $filas = $conciliaciones->get($solicitud->id, collect());
            $asignado = $this->sumarAsignado($filas);
            $objetivo = $this->importeObjetivo($solicitud);
            $estado = $this->estadoCobertura($asignado, $objetivo, $filas);

            return [
                'solicitud_id' => $solicitud->id,
                'numero_cliente' => $solicitud->cliente?->numero_cliente,
                'nombre' => $solicitud->cliente?->nombre,
                'cliente_id' => $solicitud->cliente_id,
                'monto_cotizado' => (string) $solicitud->monto_cotizado,
                'monto_aplicado' => (string) $solicitud->monto_aplicado_al_cliente,
                'importe_objetivo' => $objetivo,
                'importe_asignado' => $asignado,
                'estado_cobertura' => $estado,
                'numero_remision' => $solicitud->numero_remision,
                'fecha_operacion' => $solicitud->fecha_operacion?->toDateString(),
                'conciliaciones' => $filas->map(fn (EscalonamientoConciliacionSolicitud $fila) => [
                    'id' => $fila->id,
                    'documento_venta_id' => $fila->documento_venta_id,
                    'importe_asignado' => (string) $fila->importe_asignado,
                    'estado' => $fila->estado,
                    'folio' => $fila->documento?->folio,
                ])->values()->all(),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sugerir(SolicitudTag $solicitud, EscalonamientoPeriodo $periodo): array
    {
        if (! $solicitud->cliente_id) {
            return [];
        }

        $objetivo = $this->importeObjetivo($solicitud);
        $folios = array_filter([
            trim((string) $solicitud->numero_remision),
        ]);

        $documentos = DocumentoVenta::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('cliente_id', $solicitud->cliente_id)
            ->where('tipo', 'remision')
            ->where('estado', 'activo')
            ->where('moneda', 'MXN')
            ->orderByDesc('fecha_emision')
            ->limit(30)
            ->get();

        return $documentos
            ->map(function (DocumentoVenta $doc) use ($folios, $objetivo) {
                $puntaje = 0;
                if (in_array($doc->folio, $folios, true)) {
                    $puntaje += 100;
                }
                if (bccomp((string) $doc->total, $objetivo, 2) === 0) {
                    $puntaje += 50;
                } elseif (bccomp((string) $doc->total, $objetivo, 2) > 0) {
                    $puntaje += 20;
                }

                return [
                    'documento_venta_id' => $doc->id,
                    'folio' => $doc->folio,
                    'total' => (string) $doc->total,
                    'fecha_emision' => $doc->fecha_emision?->toDateString(),
                    'puntaje' => $puntaje,
                ];
            })
            ->sortByDesc('puntaje')
            ->values()
            ->take(10)
            ->all();
    }

    public function asignar(
        EscalonamientoPeriodo $periodo,
        SolicitudTag $solicitud,
        DocumentoVenta $documento,
        string $importeAsignado,
        ?string $evidencia,
        ?User $usuario,
    ): EscalonamientoConciliacionSolicitud {
        if ((int) $documento->cliente_id !== (int) $solicitud->cliente_id) {
            throw new InvalidArgumentException('El documento no pertenece al cliente de la solicitud.');
        }
        if ($documento->escalonamiento_periodo_id !== $periodo->id) {
            throw new InvalidArgumentException('El documento pertenece a otro período.');
        }

        $importe = $this->dinero($importeAsignado);
        if (bccomp($importe, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El importe asignado debe ser positivo.');
        }

        return DB::transaction(function () use ($periodo, $solicitud, $documento, $importe, $evidencia, $usuario) {
            $documento = DocumentoVenta::query()->lockForUpdate()->findOrFail($documento->id);
            if ((int) $documento->cliente_id !== (int) $solicitud->cliente_id) {
                throw new InvalidArgumentException('El documento no pertenece al cliente de la solicitud.');
            }
            if ((int) $documento->escalonamiento_periodo_id !== (int) $periodo->id) {
                throw new InvalidArgumentException('El documento pertenece a otro período.');
            }
            if (bccomp($importe, $this->dinero($documento->total), 2) > 0) {
                throw new InvalidArgumentException('El importe asignado supera el total del documento.');
            }

            $fila = EscalonamientoConciliacionSolicitud::query()->firstOrNew([
                'solicitud_tag_id' => $solicitud->id,
                'documento_venta_id' => $documento->id,
            ]);
            $libre = $this->disponible($documento, $fila->exists ? (int) $fila->id : null);
            if (bccomp($importe, $libre, 2) > 0) {
                throw new InvalidArgumentException('El importe asignado supera el disponible del documento.');
            }

            $fila->escalonamiento_periodo_id = $periodo->id;
            $fila->importe_asignado = $importe;
            $fila->estado = self::ESTADO_FILA_ACTIVA;
            $fila->procedencia = EscalonamientoConciliacionSolicitud::PROCEDENCIA_DOCUMENTO;
            $fila->inclusion_acumulado = $this->inclusionDe($documento);
            $fila->clave_asignacion = $this->claveDocumento($documento);
            $fila->evidencia = $evidencia;
            $fila->user_id = $usuario?->id;
            $fila->save();
            $this->reemplazarPlaceholder($solicitud);

            $this->sincronizarIncidenciaCobertura($periodo, $solicitud);

            return $fila;
        });
    }

    /**
     * Registra la conciliación documental. No modifica cubierto_por_carga_masiva ni monto_venta_actual.
     * Sin identidad y monto verificables, deja la fila pendiente o indeterminada.
     */
    public function conciliar(SolicitudTag $solicitud, ?User $usuario = null): EscalonamientoConciliacionSolicitud
    {
        try {
            return $this->conciliarUnaVez($solicitud, $usuario);
        } catch (UniqueConstraintViolationException $excepcion) {
            $existente = EscalonamientoConciliacionSolicitud::query()
                ->where('solicitud_tag_id', $solicitud->id)
                ->orderByDesc('id')
                ->first();
            if ($existente) {
                return $existente;
            }

            throw $excepcion;
        }
    }

    public function liberar(SolicitudTag $solicitud): void
    {
        DB::transaction(function () use ($solicitud) {
            SolicitudTag::query()->lockForUpdate()->find($solicitud->id);
            EscalonamientoConciliacionSolicitud::query()
                ->where('solicitud_tag_id', $solicitud->id)
                ->where('estado', self::ESTADO_FILA_ACTIVA)
                ->lockForUpdate()
                ->update(['estado' => EscalonamientoConciliacionSolicitud::ESTADO_ANULADA]);
        });
    }

    public function inclusionDe(DocumentoVenta $documento): string
    {
        if (! $this->autoridad->estaActiva()) {
            // El CSV no identifica documentos: no hay forma de afirmar que este monto ya está en el acumulado.
            return EscalonamientoConciliacionSolicitud::INCLUSION_INDETERMINADA;
        }

        $periodo = $this->autoridad->periodoOperativo();
        if (! $periodo || (int) $documento->escalonamiento_periodo_id !== (int) $periodo->id) {
            return EscalonamientoConciliacionSolicitud::INCLUSION_FUERA;
        }

        $efecto = $documento->movimiento()->value('efecto');
        if ($efecto === null || bccomp($this->dinero($efecto), '0.00', 2) === 0) {
            return EscalonamientoConciliacionSolicitud::INCLUSION_FUERA;
        }

        return EscalonamientoConciliacionSolicitud::INCLUSION_INCLUIDA;
    }

    public function quitar(EscalonamientoConciliacionSolicitud $fila): void
    {
        DB::transaction(function () use ($fila) {
            $solicitud = $fila->solicitud;
            $periodo = $fila->periodo;
            $fila->delete();
            if ($solicitud && $periodo) {
                $this->sincronizarIncidenciaCobertura($periodo, $solicitud);
            }
        });
    }

    public function sincronizarIncidenciaCobertura(EscalonamientoPeriodo $periodo, SolicitudTag $solicitud): void
    {
        $filas = EscalonamientoConciliacionSolicitud::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('solicitud_tag_id', $solicitud->id)
            ->get();

        $asignado = $this->sumarAsignado($filas);
        $objetivo = $this->importeObjetivo($solicitud);
        $estado = $this->estadoCobertura($asignado, $objetivo, $filas);

        EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('solicitud_tag_id', $solicitud->id)
            ->whereIn('codigo', ['solicitud_sin_cobertura', 'cobertura_parcial'])
            ->where('estado', 'abierta')
            ->update(['estado' => 'resuelta', 'resolucion' => 'Cobertura actualizada por conciliación.']);

        if ($estado === self::ESTADO_CUBIERTA) {
            return;
        }

        $codigo = $estado === self::ESTADO_PARCIAL ? 'cobertura_parcial' : 'solicitud_sin_cobertura';
        $motivo = $estado === self::ESTADO_PARCIAL
            ? "Solicitud #{$solicitud->id}: cobertura parcial ({$asignado} de {$objetivo})."
            : "Solicitud #{$solicitud->id}: sin documento que cubra el importe objetivo ({$objetivo}).";

        EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $solicitud->cliente_id,
            'solicitud_tag_id' => $solicitud->id,
            'gravedad' => 'aviso',
            'codigo' => $codigo,
            'motivo' => $motivo,
            'estado' => 'abierta',
        ]);
    }

    private const ESTADO_CUBIERTA = 'cubierta';

    private const ESTADO_PARCIAL = 'parcial';

    private const ESTADO_PENDIENTE = 'pendiente';

    private const ESTADO_FILA_ACTIVA = 'asignada';

    public function importeObjetivo(SolicitudTag $solicitud): string
    {
        $aplicado = bcadd((string) $solicitud->monto_aplicado_al_cliente, '0', 2);
        if (bccomp($aplicado, '0.00', 2) > 0) {
            return $aplicado;
        }
        $tentativo = $solicitud->monto_final_tentativo !== null
            ? bcadd((string) $solicitud->monto_final_tentativo, '0', 2)
            : '0.00';
        if (bccomp($tentativo, '0.00', 2) > 0) {
            return $tentativo;
        }

        return bcadd((string) $solicitud->monto_cotizado, '0', 2);
    }

    /**
     * @param  Collection<int, EscalonamientoConciliacionSolicitud>  $filas
     */
    private function sumarAsignado(Collection $filas): string
    {
        $total = '0.00';
        foreach ($filas as $fila) {
            if ($fila->estado !== self::ESTADO_FILA_ACTIVA) {
                continue;
            }
            $total = bcadd($total, (string) $fila->importe_asignado, 2);
        }

        return $total;
    }

    /**
     * @param  Collection<int, EscalonamientoConciliacionSolicitud>  $filas
     */
    private function estadoCobertura(string $asignado, string $objetivo, Collection $filas): string
    {
        if ($filas->isEmpty()) {
            return self::ESTADO_PENDIENTE;
        }
        if (bccomp($asignado, $objetivo, 2) >= 0) {
            return self::ESTADO_CUBIERTA;
        }
        if (bccomp($asignado, '0.00', 2) > 0) {
            return self::ESTADO_PARCIAL;
        }

        return self::ESTADO_PENDIENTE;
    }

    private function conciliarUnaVez(SolicitudTag $solicitud, ?User $usuario): EscalonamientoConciliacionSolicitud
    {
        return DB::transaction(function () use ($solicitud, $usuario) {
            $solicitud = SolicitudTag::query()->lockForUpdate()->findOrFail($solicitud->id);
            $solicitud->load('estado');

            if ($this->excluida($solicitud)) {
                $this->liberar($solicitud);

                return $this->guardar(
                    $solicitud, null, EscalonamientoConciliacionSolicitud::CLAVE_SIN_DOCUMENTO,
                    EscalonamientoConciliacionSolicitud::ESTADO_ANULADA, '0.00',
                    EscalonamientoConciliacionSolicitud::PROCEDENCIA_IDENTIDAD, null,
                    'Solicitud cancelada, rechazada o vencida. No consume documento.', $usuario,
                );
            }

            $documentos = $this->documentosVerificables($solicitud);
            if ($documentos->count() > 1) {
                return $this->guardar(
                    $solicitud, null, EscalonamientoConciliacionSolicitud::CLAVE_SIN_DOCUMENTO,
                    EscalonamientoConciliacionSolicitud::ESTADO_INDETERMINADA, '0.00',
                    EscalonamientoConciliacionSolicitud::PROCEDENCIA_AMBIGUA, null,
                    'Hay más de un documento con la misma identidad. No se eligió uno.', $usuario,
                );
            }

            $documento = $documentos->first();
            if (! $documento) {
                $marca = (bool) $solicitud->cubierto_por_carga_masiva;
                $folio = trim((string) $solicitud->numero_remision);

                return $this->guardar(
                    $solicitud, null, EscalonamientoConciliacionSolicitud::CLAVE_SIN_DOCUMENTO,
                    $marca || $folio !== ''
                        ? EscalonamientoConciliacionSolicitud::ESTADO_INDETERMINADA
                        : EscalonamientoConciliacionSolicitud::ESTADO_PENDIENTE,
                    '0.00',
                    $marca
                        ? EscalonamientoConciliacionSolicitud::PROCEDENCIA_HISTORICA
                        : EscalonamientoConciliacionSolicitud::PROCEDENCIA_IDENTIDAD,
                    null,
                    $marca
                        ? 'Marca histórica sin documento verificable. No acredita ni se revierte.'
                        : 'No hay documento con identidad y monto verificables.',
                    $usuario,
                );
            }

            if (! $solicitud->pago_confirmado) {
                return $this->guardar(
                    $solicitud, $documento, $this->claveDocumento($documento),
                    EscalonamientoConciliacionSolicitud::ESTADO_PENDIENTE, '0.00',
                    EscalonamientoConciliacionSolicitud::PROCEDENCIA_PAGO_PENDIENTE,
                    $this->inclusionDe($documento),
                    'El documento identifica la operación, pero el pago no está confirmado. No consume disponible.',
                    $usuario,
                );
            }

            $objetivo = $this->importeObjetivo($solicitud);
            $fila = EscalonamientoConciliacionSolicitud::query()->firstOrNew([
                'solicitud_tag_id' => $solicitud->id,
                'clave_asignacion' => $this->claveDocumento($documento),
            ]);
            $libre = $this->disponible($documento, $fila->exists ? (int) $fila->id : null);
            if (bccomp($objetivo, '0.00', 2) <= 0 || bccomp($libre, '0.00', 2) <= 0) {
                return $this->guardar(
                    $solicitud, $documento, $this->claveDocumento($documento),
                    EscalonamientoConciliacionSolicitud::ESTADO_SIN_DISPONIBILIDAD, '0.00',
                    EscalonamientoConciliacionSolicitud::PROCEDENCIA_DOCUMENTO,
                    $this->inclusionDe($documento),
                    'El documento no tiene monto disponible para esta solicitud.',
                    $usuario,
                );
            }

            $importe = bccomp($objetivo, $libre, 2) <= 0 ? $objetivo : $libre;

            return $this->guardar(
                $solicitud, $documento, $this->claveDocumento($documento),
                self::ESTADO_FILA_ACTIVA, $importe,
                EscalonamientoConciliacionSolicitud::PROCEDENCIA_DOCUMENTO,
                $this->inclusionDe($documento),
                'Asignación documental dentro del disponible del documento.',
                $usuario,
            );
        });
    }

    private function guardar(
        SolicitudTag $solicitud,
        ?DocumentoVenta $documento,
        string $clave,
        string $estado,
        string $importe,
        string $procedencia,
        ?string $inclusion,
        string $evidencia,
        ?User $usuario,
    ): EscalonamientoConciliacionSolicitud {
        $fila = EscalonamientoConciliacionSolicitud::query()->firstOrNew([
            'solicitud_tag_id' => $solicitud->id,
            'clave_asignacion' => $clave,
        ]);
        $fila->documento_venta_id = $documento?->id;
        $fila->escalonamiento_periodo_id = $documento?->escalonamiento_periodo_id;
        $fila->importe_asignado = $importe;
        $fila->estado = $estado;
        $fila->procedencia = $procedencia;
        $fila->inclusion_acumulado = $inclusion;
        $fila->evidencia = $evidencia;
        if ($usuario) {
            $fila->user_id = $usuario->id;
        }
        $fila->save();
        if ($documento && $estado === self::ESTADO_FILA_ACTIVA) {
            $this->reemplazarPlaceholder($solicitud);
        }

        return $fila;
    }

    private function reemplazarPlaceholder(SolicitudTag $solicitud): void
    {
        EscalonamientoConciliacionSolicitud::query()
            ->where('solicitud_tag_id', $solicitud->id)
            ->where('clave_asignacion', EscalonamientoConciliacionSolicitud::CLAVE_SIN_DOCUMENTO)
            ->where('estado', '!=', EscalonamientoConciliacionSolicitud::ESTADO_REEMPLAZADA)
            ->update([
                'estado' => EscalonamientoConciliacionSolicitud::ESTADO_REEMPLAZADA,
                'importe_asignado' => '0.00',
            ]);
    }

    /**
     * @return Collection<int, DocumentoVenta>
     */
    private function documentosVerificables(SolicitudTag $solicitud): Collection
    {
        $folio = trim((string) $solicitud->numero_remision);
        if ($folio === '' || ! $solicitud->cliente_id) {
            return collect();
        }

        return DocumentoVenta::query()
            ->where('cliente_id', $solicitud->cliente_id)
            ->where('estado', 'activo')
            ->where('moneda', 'MXN')
            ->where('total', '>', 0)
            ->where(function ($consulta) use ($folio) {
                $consulta->where('folio', $folio)->orWhere('remision_original', $folio);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function disponible(DocumentoVenta $documento, ?int $exceptoFilaId): string
    {
        $usado = '0.00';
        $filas = EscalonamientoConciliacionSolicitud::query()
            ->where('documento_venta_id', $documento->id)
            ->where('estado', self::ESTADO_FILA_ACTIVA)
            ->when($exceptoFilaId, fn ($consulta) => $consulta->where('id', '!=', $exceptoFilaId))
            ->lockForUpdate()
            ->get();
        foreach ($filas as $fila) {
            $usado = bcadd($usado, $this->dinero($fila->importe_asignado), 2);
        }
        $libre = bcsub($this->dinero($documento->total), $usado, 2);

        return bccomp($libre, '0.00', 2) > 0 ? $libre : '0.00';
    }

    private function excluida(SolicitudTag $solicitud): bool
    {
        $nombre = $solicitud->estado?->nombre;
        if ($nombre === null && $solicitud->catalogo_estado_solicitud_id) {
            $nombre = CatalogoEstadoSolicitud::query()
                ->whereKey($solicitud->catalogo_estado_solicitud_id)
                ->value('nombre');
        }

        return in_array($nombre, ['Cancelada', 'Incorrecta', 'Rechazada'], true);
    }

    private function claveDocumento(DocumentoVenta $documento): string
    {
        return 'doc:'.$documento->id;
    }

    private function dinero(float|int|string|null $valor): string
    {
        if ($valor === null || $valor === '') {
            return '0.00';
        }
        if (is_int($valor) || is_float($valor)) {
            $valor = number_format($valor, 2, '.', '');
        }

        return bcadd((string) $valor, '0', 2);
    }
}
