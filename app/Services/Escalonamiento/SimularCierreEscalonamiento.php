<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\User;
use App\Services\Escalonamiento\Excepciones\CierreEscalonamientoException;
use Illuminate\Support\Facades\DB;

class SimularCierreEscalonamiento
{
    public function __construct(
        private HashSnapshotPeriodoEscalonamiento $hashSnapshot,
        private ConstruirFilasCierreCliente $construirFilas,
        private ConciliacionSolicitudEscalonamiento $conciliacion,
    ) {}

    /**
     * @return array{cierre: EscalonamientoCierre, advertencias: list<string>, bloqueos: list<string>}
     */
    public function simular(EscalonamientoPeriodo $periodo, User $usuario): array
    {
        return DB::transaction(function () use ($periodo, $usuario) {
            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);

            if (! $periodo->permiteSimularCierre()) {
                throw new CierreEscalonamientoException('Solo se puede simular cierre de un período abierto o histórico sin cierre aplicado.');
            }

            $reconstruccion = $periodo->estado === EscalonamientoPeriodo::ESTADO_HISTORIAL;

            $bloqueos = $this->evaluarBloqueos($periodo);
            if ($bloqueos !== []) {
                throw new CierreEscalonamientoException(implode(' ', $bloqueos));
            }

            $version = (int) EscalonamientoCierre::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->max('version') + 1;

            $hash = $this->hashSnapshot->calcular($periodo);
            $filas = $this->construirFilas->construir($periodo);

            $cierre = EscalonamientoCierre::create([
                'escalonamiento_periodo_id' => $periodo->id,
                'version' => $version,
                'estado' => EscalonamientoCierre::ESTADO_BORRADOR,
                'reconstruccion' => $reconstruccion,
                'hash_snapshot' => $hash,
                'simulado_en' => now(),
                'simulado_por_user_id' => $usuario->id,
            ]);

            $this->construirFilas->persistirDetalles($cierre->id, $filas);

            $periodo->estado = EscalonamientoPeriodo::ESTADO_EN_REVISION;
            $periodo->escalonamiento_cierre_vigente_id = $cierre->id;
            $periodo->save();

            return [
                'cierre' => $cierre->load('detalles.cliente'),
                'advertencias' => $this->advertencias($periodo),
                'bloqueos' => [],
            ];
        });
    }

    /**
     * @return list<string>
     */
    private function evaluarBloqueos(EscalonamientoPeriodo $periodo): array
    {
        $bloqueos = [];

        $pendientes = DocumentoVenta::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('tipo', 'devolucion')
            ->where('estado', 'pendiente_de_vinculacion')
            ->count();
        if ($pendientes > 0) {
            $bloqueos[] = "Hay {$pendientes} devolución(es) pendientes de vínculo.";
        }

        $codigos = config('escalonamiento.incidencias_bloquean_autorizacion', []);
        $incidencias = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('estado', 'abierta')
            ->when($codigos !== [], fn ($q) => $q->whereIn('codigo', $codigos))
            ->count();
        if ($incidencias > 0) {
            $bloqueos[] = "Hay {$incidencias} incidencia(s) abiertas que bloquean el cierre.";
        }

        return $bloqueos;
    }

    /**
     * @return list<string>
     */
    private function advertencias(EscalonamientoPeriodo $periodo): array
    {
        $advertencias = [];
        foreach ($this->conciliacion->listarSolicitudesPeriodo($periodo) as $fila) {
            if (($fila['estado_cobertura'] ?? '') !== 'completa') {
                $advertencias[] = "Solicitud #{$fila['solicitud_id']} sin cobertura completa.";
            }
        }

        return $advertencias;
    }
}
