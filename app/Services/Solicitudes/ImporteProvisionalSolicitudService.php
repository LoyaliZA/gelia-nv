<?php

namespace App\Services\Solicitudes;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\ImporteProvisionalSolicitud;
use App\Models\SolicitudTag;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Un provisional vigente por solicitud. No escribe monto_venta_actual ni la lista.
 * Un corte cierra la fila; no la declara conciliada.
 */
class ImporteProvisionalSolicitudService
{
    public function __construct(private AcumuladoOperativoCliente $acumulado) {}

    public function registrarCotizacion(SolicitudTag $solicitud, ?int $usuarioId): void
    {
        if (! $solicitud->exists || ! $this->admiteImporte($solicitud, (string) $solicitud->monto_cotizado)) {
            return;
        }
        if ($this->vigente($solicitud) || $this->tieneCierrePorCorte($solicitud)) {
            return;
        }

        $this->insertar(
            $solicitud,
            $this->decimal($solicitud->monto_cotizado),
            ImporteProvisionalSolicitud::TIPO_COTIZACION,
            'aprobacion',
            $usuarioId,
            ['evento' => 'cotizacion_aprobada'],
        );
    }

    /**
     * La cotización vigente se sustituye por el pago. Si un corte ya cerró esa
     * solicitud, la fecha de confirmación no reabre el importe.
     */
    public function sustituirPorPago(SolicitudTag $solicitud, string $importe, ?int $usuarioId): void
    {
        if (! $solicitud->exists) {
            return;
        }
        $solicitud->loadMissing('proceso');
        if ($solicitud->proceso?->esOperativo() || CrearSolicitudService::esFlujoTienda($solicitud)) {
            return;
        }
        if (bccomp($this->decimal($importe), '0', 2) <= 0) {
            $this->revocarSiVigente($solicitud, $usuarioId, 'pago_en_cero');

            return;
        }

        $vigente = $this->vigente($solicitud);
        if ($vigente) {
            $anterior = (string) $vigente->importe;
            $aprobacion = $vigente->fecha_operacion;
            $confirmacion = now();
            $vigente->tipo = ImporteProvisionalSolicitud::TIPO_PAGO;
            $vigente->importe = $this->decimal($importe);
            $vigente->fecha_confirmacion = $confirmacion;
            $vigente->usuario_id = $usuarioId;
            $vigente->trazabilidad = $this->anotar($vigente, [
                'evento' => 'pago_sustituye_cotizacion',
                'importe_anterior' => $anterior,
                'importe_pagado' => $this->decimal($importe),
                'fecha_aprobacion' => $aprobacion?->format('Y-m-d H:i:s'),
                'fecha_confirmacion' => $confirmacion->format('Y-m-d H:i:s'),
            ]);
            $vigente->save();

            return;
        }

        $cerrado = $this->ultimoCierre($solicitud);
        if ($cerrado) {
            $cerrado->inclusion = ImporteProvisionalSolicitud::INCLUSION_INDETERMINADA;
            $cerrado->usuario_id = $usuarioId;
            $cerrado->trazabilidad = $this->anotar($cerrado, [
                'evento' => 'pago_posterior_sin_reapertura',
                'importe_pagado' => $this->decimal($importe),
                'inclusion' => ImporteProvisionalSolicitud::INCLUSION_INDETERMINADA,
            ]);
            $cerrado->save();

            return;
        }

        $this->insertar(
            $solicitud,
            $this->decimal($importe),
            ImporteProvisionalSolicitud::TIPO_PAGO,
            'pago_confirmado',
            $usuarioId,
            ['evento' => 'pago_confirmado'],
        );
    }

    public function revocarSiVigente(SolicitudTag $solicitud, ?int $usuarioId, string $motivo): bool
    {
        $vigente = $this->vigente($solicitud);
        if (! $vigente) {
            return false;
        }

        $vigente->estado = ImporteProvisionalSolicitud::ESTADO_REVOCADO;
        $vigente->clave_vigente = null;
        $vigente->usuario_id = $usuarioId;
        $vigente->trazabilidad = $this->anotar($vigente, ['evento' => 'revocado', 'motivo' => $motivo]);
        $vigente->save();

        return true;
    }

    public function tieneCierrePorCorte(SolicitudTag $solicitud): bool
    {
        return $this->ultimoCierre($solicitud) !== null;
    }

    public function cerrarPorCorte(Cliente $cliente, Carbon $corte, int $importacionId, ?int $usuarioId): int
    {
        $instante = $corte->format('Y-m-d H:i:s');
        $vigentes = ImporteProvisionalSolicitud::query()
            ->where('cliente_id', $cliente->id)
            ->where('estado', ImporteProvisionalSolicitud::ESTADO_VIGENTE)
            ->where(function ($consulta) use ($instante) {
                $consulta->where(function ($consulta) use ($instante) {
                    $consulta->whereNotNull('fecha_confirmacion')
                        ->where('fecha_confirmacion', '<=', $instante);
                })->orWhere(function ($consulta) use ($instante) {
                    $consulta->whereNull('fecha_confirmacion')
                        ->where('fecha_operacion', '<=', $instante);
                });
            })
            ->lockForUpdate()
            ->get();

        foreach ($vigentes as $fila) {
            $fila->estado = ImporteProvisionalSolicitud::ESTADO_CERRADO;
            $fila->clave_vigente = null;
            $fila->importacion_cliente_id = $importacionId;
            $fila->fecha_corte = $corte;
            $fila->usuario_id = $usuarioId;
            $fila->inclusion = ImporteProvisionalSolicitud::INCLUSION_INDETERMINADA;
            $fila->trazabilidad = $this->anotar($fila, [
                'evento' => 'cerrado_por_corte',
                'fecha_corte' => $corte->format('Y-m-d H:i:s'),
                'importacion_cliente_id' => $importacionId,
                'inclusion' => ImporteProvisionalSolicitud::INCLUSION_INDETERMINADA,
            ]);
            $fila->save();
        }

        return $vigentes->count();
    }

    public function registrarInicial(SolicitudTag $solicitud, string $importe, string $tipo): ?ImporteProvisionalSolicitud
    {
        if (! $solicitud->exists || ! $this->admiteImporte($solicitud, $importe)) {
            return null;
        }
        if (ImporteProvisionalSolicitud::query()->where('solicitud_tag_id', $solicitud->id)->exists()) {
            return null;
        }

        $fila = $this->insertar($solicitud, $this->decimal($importe), $tipo, 'reconciliacion_inicial', null, [
            'evento' => 'reconciliacion_inicial',
        ]);
        $fila->fecha_operacion = $solicitud->created_at ?? now();
        $fila->save();

        return $fila;
    }

    /** Única vía para abrir un provisional después de un cierre. Exige evidencia. */
    public function abrirCorreccionAdministrativa(
        SolicitudTag $solicitud,
        string $importe,
        string $evidencia,
        ?int $usuarioId,
    ): ImporteProvisionalSolicitud {
        $evidencia = trim($evidencia);
        if ($evidencia === '') {
            throw ValidationException::withMessages([
                'evidencia' => 'La corrección exige evidencia de una venta posterior al corte y no incluida en el oficial.',
            ]);
        }
        if ($this->vigente($solicitud)) {
            throw ValidationException::withMessages([
                'solicitud' => 'Esta solicitud ya tiene un provisional vigente.',
            ]);
        }

        return $this->insertar(
            $solicitud,
            $this->decimal($importe),
            ImporteProvisionalSolicitud::TIPO_PAGO,
            'correccion_administrativa',
            $usuarioId,
            [
                'evento' => 'correccion_administrativa',
                'evidencia' => $evidencia,
            ],
        );
    }

    /**
     * Oficial de la columna, más los demás vigentes, más el pago que sustituye
     * la cotización. Nunca suma cotización y pago. Un cierre no vuelve a sumar.
     */
    public function baseDecisionLista(Cliente $cliente, ?SolicitudTag $enCurso, string $importeEnCurso): string
    {
        $oficial = $this->acumulado->oficial($cliente);
        $provisional = $this->acumulado->provisional($cliente->id);
        if (! $enCurso || ! $this->admiteImporte($enCurso, $importeEnCurso)) {
            return bcadd($oficial, $provisional, 2);
        }

        $vigente = $this->vigente($enCurso);
        if ($vigente) {
            $sinEsta = bcsub($provisional, (string) $vigente->importe, 2);
            if (bccomp($sinEsta, '0', 2) < 0) {
                $sinEsta = '0.00';
            }

            return bcadd(bcadd($oficial, $sinEsta, 2), $this->decimal($importeEnCurso), 2);
        }

        if ($this->tieneCierrePorCorte($enCurso)) {
            return bcadd($oficial, $provisional, 2);
        }

        return bcadd(bcadd($oficial, $provisional, 2), $this->decimal($importeEnCurso), 2);
    }

    /**
     * El documento ya está dentro del oficial. Se suman los demás vigentes que
     * la conciliación todavía no incluyó, y no se vuelve a sumar este pago.
     */
    public function baseConDocumentoIncluido(Cliente $cliente, SolicitudTag $enCurso, string $oficial): string
    {
        $vigentes = ImporteProvisionalSolicitud::query()
            ->where('cliente_id', $cliente->id)
            ->where('estado', ImporteProvisionalSolicitud::ESTADO_VIGENTE)
            ->get(['solicitud_tag_id', 'importe']);
        $incluidas = EscalonamientoConciliacionSolicitud::query()
            ->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA)
            ->where('inclusion_acumulado', EscalonamientoConciliacionSolicitud::INCLUSION_INCLUIDA)
            ->whereIn('solicitud_tag_id', $vigentes->pluck('solicitud_tag_id'))
            ->pluck('solicitud_tag_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $excluir = array_merge($incluidas, [(int) $enCurso->id]);
        $suma = '0.00';
        foreach ($vigentes as $fila) {
            if (in_array((int) $fila->solicitud_tag_id, $excluir, true)) {
                continue;
            }
            $suma = bcadd($suma, (string) $fila->importe, 2);
        }

        return bcadd($this->decimal($oficial), $suma, 2);
    }

    private function admiteImporte(SolicitudTag $solicitud, string $importe): bool
    {
        $solicitud->loadMissing('proceso');
        if ($solicitud->proceso?->esOperativo() || CrearSolicitudService::esFlujoTienda($solicitud)) {
            return false;
        }

        return bccomp($this->decimal($importe), '0', 2) > 0;
    }

    private function vigente(SolicitudTag $solicitud): ?ImporteProvisionalSolicitud
    {
        return ImporteProvisionalSolicitud::query()
            ->where('solicitud_tag_id', $solicitud->id)
            ->where('estado', ImporteProvisionalSolicitud::ESTADO_VIGENTE)
            ->lockForUpdate()
            ->first();
    }

    private function ultimoCierre(SolicitudTag $solicitud): ?ImporteProvisionalSolicitud
    {
        return ImporteProvisionalSolicitud::query()
            ->where('solicitud_tag_id', $solicitud->id)
            ->where('estado', ImporteProvisionalSolicitud::ESTADO_CERRADO)
            ->lockForUpdate()
            ->latest('id')
            ->first();
    }

    private function insertar(
        SolicitudTag $solicitud,
        string $importe,
        string $tipo,
        string $origen,
        ?int $usuarioId,
        array $evento,
    ): ImporteProvisionalSolicitud {
        try {
            return ImporteProvisionalSolicitud::query()->create([
                'cliente_id' => $solicitud->cliente_id,
                'solicitud_tag_id' => $solicitud->id,
                'importe' => $importe,
                'tipo' => $tipo,
                'estado' => ImporteProvisionalSolicitud::ESTADO_VIGENTE,
                'fecha_operacion' => now(),
                'fecha_confirmacion' => $tipo === ImporteProvisionalSolicitud::TIPO_PAGO ? now() : null,
                'origen' => $origen,
                'usuario_id' => $usuarioId,
                'clave_operacion' => $solicitud->id.'-'.str_replace('.', '', uniqid('', true)),
                'clave_vigente' => 'solicitud:'.$solicitud->id,
                'trazabilidad' => [$evento],
            ]);
        } catch (QueryException $e) {
            $vigente = ImporteProvisionalSolicitud::query()
                ->where('clave_vigente', 'solicitud:'.$solicitud->id)
                ->first();
            if ($vigente && str_contains($e->getMessage(), 'clave_vigente')) {
                return $vigente;
            }

            throw $e;
        }
    }

    private function anotar(ImporteProvisionalSolicitud $fila, array $evento): array
    {
        $eventos = $fila->trazabilidad ?? [];

        return array_merge($eventos, [$evento]);
    }

    private function decimal(mixed $valor): string
    {
        return bcadd((string) ($valor ?? '0'), '0', 2);
    }
}
