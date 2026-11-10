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
            ->filter(fn (DocumentoVenta $doc) => $this->motivoInelegible($solicitud, $doc, (int) $periodo->id) === null)
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
        $importe = $this->dinero($importeAsignado);
        if (bccomp($importe, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El importe asignado debe ser positivo.');
        }

        return DB::transaction(function () use ($periodo, $solicitud, $documento, $importe, $evidencia, $usuario) {
            $solicitud = SolicitudTag::query()->lockForUpdate()->findOrFail($solicitud->id);
            $solicitud->load('estado');
            if ($this->excluida($solicitud)) {
                throw new InvalidArgumentException('La solicitud está cancelada, rechazada o vencida. No se asigna cobertura.');
            }
            if (! $solicitud->pago_confirmado) {
                throw new InvalidArgumentException('El pago de la solicitud no está confirmado. La asignación manual no consume el documento.');
            }

            $bloqueados = $this->bloquearCandidatos($solicitud, (int) $documento->id);
            $documento = $bloqueados->firstWhere('id', $documento->id);
            if (! $documento) {
                throw new InvalidArgumentException('El documento ya no existe.');
            }
            $motivo = $this->motivoInelegible($solicitud, $documento, (int) $periodo->id);
            if ($motivo !== null) {
                throw new InvalidArgumentException($motivo);
            }
            $verificables = $bloqueados
                ->filter(fn (DocumentoVenta $candidato) => $this->motivoInelegible($solicitud, $candidato, null) === null)
                ->values();
            if ($verificables->count() !== 1) {
                throw new InvalidArgumentException('Hay más de un documento con la misma identidad. No se eligió uno.');
            }

            $objetivo = $this->importeObjetivo($solicitud);
            if (bccomp($importe, $objetivo, 2) > 0) {
                throw new InvalidArgumentException('El importe asignado supera el monto confirmado de la solicitud.');
            }

            $this->anularCoberturaAjena($solicitud, (int) $documento->id);

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
            $fila->evidencia = $this->anexarEvidencia($fila->exists ? $fila->evidencia : null, (string) $evidencia);
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
            $this->anularCoberturaAjena($solicitud, null);
        });
    }

    /**
     * Retira cobertura activa que ya no tiene identidad verificable o que excede el monto confirmado.
     * No crea una asignación nueva ni modifica monto_venta_actual ni la marca histórica.
     */
    public function resolverObsolescencia(SolicitudTag $solicitud): void
    {
        DB::transaction(function () use ($solicitud) {
            $solicitud = SolicitudTag::withTrashed()->lockForUpdate()->find($solicitud->id);
            if (! $solicitud) {
                return;
            }
            $solicitud->load('estado');
            if ($this->excluida($solicitud) || ! $solicitud->pago_confirmado) {
                $this->anularCoberturaAjena($solicitud, null);

                return;
            }

            $documentos = $this->documentosVerificables($solicitud);
            if ($documentos->count() !== 1) {
                $this->anularCoberturaAjena($solicitud, null);

                return;
            }

            $documento = $documentos->first();
            $this->anularCoberturaAjena($solicitud, (int) $documento->id);
            $fila = EscalonamientoConciliacionSolicitud::query()
                ->where('solicitud_tag_id', $solicitud->id)
                ->where('documento_venta_id', $documento->id)
                ->where('estado', self::ESTADO_FILA_ACTIVA)
                ->lockForUpdate()
                ->first();
            if (! $fila) {
                return;
            }

            $objetivo = $this->importeObjetivo($solicitud);
            $libre = $this->disponible($documento, (int) $fila->id);
            $tope = bccomp($objetivo, $libre, 2) <= 0 ? $objetivo : $libre;
            $actual = $this->dinero($fila->importe_asignado);
            if (bccomp($tope, '0.00', 2) <= 0) {
                $fila->estado = EscalonamientoConciliacionSolicitud::ESTADO_ANULADA;
                $fila->evidencia = $this->anexarEvidencia(
                    $fila->evidencia,
                    'Cobertura retirada: el monto confirmado ya no sostiene la asignación.',
                );
                $fila->save();

                return;
            }
            if (bccomp($actual, $tope, 2) > 0) {
                $fila->importe_asignado = $tope;
                $fila->evidencia = $this->anexarEvidencia(
                    $fila->evidencia,
                    "Importe de cobertura actualizado de {$actual} a {$tope}.",
                );
                $fila->save();
            }
        });
    }

    public function resolverPorDocumento(int $documentoId): void
    {
        $documento = DocumentoVenta::query()->find($documentoId);
        $folios = array_values(array_filter([
            trim((string) ($documento->folio ?? '')),
            trim((string) ($documento->remision_original ?? '')),
        ]));

        $ids = EscalonamientoConciliacionSolicitud::query()
            ->where('estado', self::ESTADO_FILA_ACTIVA)
            ->where(function ($consulta) use ($documentoId, $folios) {
                $consulta->where('documento_venta_id', $documentoId);
                if ($folios !== []) {
                    $consulta->orWhereIn('solicitud_tag_id', function ($sub) use ($folios) {
                        $sub->select('id')
                            ->from('solicitudes_tags')
                            ->whereIn('numero_remision', $folios);
                    });
                }
            })
            ->orderBy('solicitud_tag_id')
            ->pluck('solicitud_tag_id')
            ->unique()
            ->sort()
            ->values();

        foreach ($ids as $solicitudId) {
            $solicitud = SolicitudTag::withTrashed()->find($solicitudId);
            if ($solicitud) {
                $this->resolverObsolescencia($solicitud);
            }
        }
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
        if ($solicitud->pago_confirmado) {
            return $this->dinero($solicitud->monto_aplicado_al_cliente);
        }
        $tentativo = $solicitud->monto_final_tentativo !== null
            ? $this->dinero($solicitud->monto_final_tentativo)
            : '0.00';
        if (bccomp($tentativo, '0.00', 2) > 0) {
            return $tentativo;
        }

        return $this->dinero($solicitud->monto_cotizado);
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
                $this->anularCoberturaAjena($solicitud, null);

                return $this->guardar(
                    $solicitud, null, EscalonamientoConciliacionSolicitud::CLAVE_SIN_DOCUMENTO,
                    EscalonamientoConciliacionSolicitud::ESTADO_ANULADA, '0.00',
                    EscalonamientoConciliacionSolicitud::PROCEDENCIA_IDENTIDAD, null,
                    'Solicitud cancelada, rechazada o vencida. No consume documento.', $usuario,
                );
            }

            $documentos = $this->documentosVerificables($solicitud);
            if ($documentos->count() > 1) {
                $this->anularCoberturaAjena($solicitud, null);

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
                $this->anularCoberturaAjena($solicitud, null);

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
                $this->anularCoberturaAjena($solicitud, (int) $documento->id);

                return $this->guardar(
                    $solicitud, $documento, $this->claveDocumento($documento),
                    EscalonamientoConciliacionSolicitud::ESTADO_PENDIENTE, '0.00',
                    EscalonamientoConciliacionSolicitud::PROCEDENCIA_PAGO_PENDIENTE,
                    $this->inclusionDe($documento),
                    'El documento identifica la operación, pero el pago no está confirmado. No consume disponible.',
                    $usuario,
                );
            }

            $this->anularCoberturaAjena($solicitud, (int) $documento->id);
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
        $fila->evidencia = $this->anexarEvidencia($fila->exists ? $fila->evidencia : null, $evidencia);
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
        return $this->bloquearCandidatos($solicitud)
            ->filter(fn (DocumentoVenta $documento) => $this->motivoInelegible($solicitud, $documento, null) === null)
            ->sortBy('id')
            ->values();
    }

    /**
     * @return Collection<int, DocumentoVenta>
     */
    private function bloquearCandidatos(SolicitudTag $solicitud, ?int $documentoExtraId = null): Collection
    {
        $folio = trim((string) $solicitud->numero_remision);
        $consulta = DocumentoVenta::query()->orderBy('id');
        if ($folio !== '' && $solicitud->cliente_id) {
            $consulta->where(function ($q) use ($solicitud, $folio, $documentoExtraId) {
                $q->where(function ($q) use ($solicitud, $folio) {
                    $q->where('cliente_id', $solicitud->cliente_id)
                        ->where(function ($q) use ($folio) {
                            $q->where('folio', $folio)->orWhere('remision_original', $folio);
                        });
                });
                if ($documentoExtraId) {
                    $q->orWhere('id', $documentoExtraId);
                }
            });
        } elseif ($documentoExtraId) {
            $consulta->whereKey($documentoExtraId);
        } else {
            return collect();
        }

        return $consulta->lockForUpdate()->get();
    }

    private function motivoInelegible(SolicitudTag $solicitud, DocumentoVenta $documento, ?int $periodoId): ?string
    {
        if (! $solicitud->cliente_id || (int) $documento->cliente_id !== (int) $solicitud->cliente_id) {
            return 'El documento no pertenece al cliente de la solicitud.';
        }
        $folio = trim((string) $solicitud->numero_remision);
        if ($folio === '') {
            return 'La solicitud no tiene un folio de remisión verificable.';
        }
        $folioDocumento = trim((string) $documento->folio);
        $original = trim((string) $documento->remision_original);
        if ($folioDocumento !== $folio && $original !== $folio) {
            return 'El documento no coincide con el folio ni con la remisión original de la solicitud.';
        }
        if ($documento->estado !== 'activo') {
            return 'El documento no está activo.';
        }
        if (strtoupper((string) $documento->moneda) !== 'MXN') {
            return 'El documento no está en moneda MXN.';
        }
        if (bccomp($this->dinero($documento->total), '0.00', 2) <= 0) {
            return 'El documento no tiene un monto positivo.';
        }
        if ($periodoId !== null && (int) $documento->escalonamiento_periodo_id !== $periodoId) {
            return 'El documento pertenece a otro período.';
        }

        return null;
    }

    private function anularCoberturaAjena(SolicitudTag $solicitud, ?int $documentoConservadoId): void
    {
        $filas = EscalonamientoConciliacionSolicitud::query()
            ->where('solicitud_tag_id', $solicitud->id)
            ->where('estado', self::ESTADO_FILA_ACTIVA)
            ->when(
                $documentoConservadoId,
                fn ($consulta) => $consulta->where(function ($q) use ($documentoConservadoId) {
                    $q->where('documento_venta_id', '!=', $documentoConservadoId)
                        ->orWhereNull('documento_venta_id');
                }),
            )
            ->lockForUpdate()
            ->get();

        foreach ($filas as $fila) {
            $fila->estado = EscalonamientoConciliacionSolicitud::ESTADO_ANULADA;
            $fila->evidencia = $this->anexarEvidencia(
                $fila->evidencia,
                'Cobertura retirada: la identidad documental ya no sostiene esta asignación.',
            );
            $fila->save();
        }
    }

    private function anexarEvidencia(?string $previa, string $nota): string
    {
        $previa = trim((string) $previa);
        $nota = trim($nota);
        if ($nota === '' || ($previa !== '' && str_contains($previa, $nota))) {
            return $previa;
        }

        return $previa === '' ? $nota : $previa."\n".$nota;
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
