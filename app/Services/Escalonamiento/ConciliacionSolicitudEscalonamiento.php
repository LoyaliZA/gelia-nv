<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\SolicitudTag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConciliacionSolicitudEscalonamiento
{
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

        $importe = bcadd($importeAsignado, '0', 2);
        if (bccomp($importe, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('El importe asignado debe ser positivo.');
        }
        if (bccomp($importe, (string) $documento->total, 2) > 0) {
            throw new InvalidArgumentException('El importe asignado supera el total del documento.');
        }

        return DB::transaction(function () use ($periodo, $solicitud, $documento, $importe, $evidencia, $usuario) {
            $fila = EscalonamientoConciliacionSolicitud::query()->firstOrNew([
                'solicitud_tag_id' => $solicitud->id,
                'documento_venta_id' => $documento->id,
            ]);
            $fila->escalonamiento_periodo_id = $periodo->id;
            $fila->importe_asignado = $importe;
            $fila->estado = self::ESTADO_FILA_ACTIVA;
            $fila->evidencia = $evidencia;
            $fila->user_id = $usuario?->id;
            $fila->save();

            $this->sincronizarIncidenciaCobertura($periodo, $solicitud);

            return $fila;
        });
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
}
