<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Models\Escalonamiento\EscalonamientoCierreDetalle;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\User;
use App\Services\Escalonamiento\Excepciones\CierreEscalonamientoException;
use Illuminate\Support\Facades\DB;

class AplicarCierreEscalonamiento
{
    public function __construct(
        private HashSnapshotPeriodoEscalonamiento $hashSnapshot,
        private AplicarCambioLista $aplicarCambioLista,
        private AbrirPeriodoSiguienteEscalonamiento $abrirSiguiente,
        private GenerarReporteAjustesErp $reporteErp,
    ) {}

    /**
     * @return array{cierre: EscalonamientoCierre, periodo_siguiente: ?EscalonamientoPeriodo, reconstruccion: bool}
     */
    public function aplicar(EscalonamientoPeriodo $periodo, User $usuario): array
    {
        return DB::transaction(function () use ($periodo, $usuario) {
            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);
            $cierre = $periodo->cierreVigente;
            if (! $cierre) {
                throw new CierreEscalonamientoException('No hay cierre autorizado para aplicar.');
            }

            $reconstruccion = (bool) $cierre->reconstruccion;

            if ($cierre->estaAplicado()) {
                return [
                    'cierre' => $cierre,
                    'periodo_siguiente' => $this->periodoSiguienteExistente($periodo),
                    'reconstruccion' => $reconstruccion,
                ];
            }

            if (! $cierre->puedeAplicarse()) {
                throw new CierreEscalonamientoException('El cierre debe estar autorizado antes de aplicar.');
            }

            if ($periodo->estado !== EscalonamientoPeriodo::ESTADO_AUTORIZADO) {
                throw new CierreEscalonamientoException('El período no está listo para aplicar el cierre.');
            }

            $hashActual = $this->hashSnapshot->calcular($periodo);
            if ($hashActual !== $cierre->hash_snapshot) {
                throw new CierreEscalonamientoException('Los datos del período cambiaron. Vuelva a simular el cierre.');
            }

            $periodo->estado = EscalonamientoPeriodo::ESTADO_APLICACION_PENDIENTE;
            $periodo->save();

            if (! $reconstruccion) {
                EscalonamientoCierreDetalle::query()
                    ->where('escalonamiento_cierre_id', $cierre->id)
                    ->orderBy('cliente_id')
                    ->chunkById(100, function ($detalles) use ($cierre) {
                        foreach ($detalles as $detalle) {
                            $this->aplicarCambioLista->aplicar($cierre, $detalle);
                            $cierre->ultimo_cliente_aplicado_id = $detalle->cliente_id;
                            $cierre->save();
                        }
                    });
            }

            $cierre->estado = EscalonamientoCierre::ESTADO_APLICADO;
            $cierre->aplicado_en = now();
            $cierre->aplicado_por_user_id = $usuario->id;
            $cierre->save();

            $periodo->estado = EscalonamientoPeriodo::ESTADO_CERRADO;
            $periodo->fecha_corte = now();
            $periodo->save();

            $this->reporteErp->generar($cierre);

            $siguiente = $this->abrirSiguiente->abrirDesdeCierre($cierre, $reconstruccion);

            return [
                'cierre' => $cierre->fresh(),
                'periodo_siguiente' => $siguiente,
                'reconstruccion' => $reconstruccion,
            ];
        });
    }

    private function periodoSiguienteExistente(EscalonamientoPeriodo $periodo): ?EscalonamientoPeriodo
    {
        $anio = (int) $periodo->anio;
        $mes = (int) $periodo->mes;
        if ($mes === 12) {
            $anio++;
            $mes = 1;
        } else {
            $mes++;
        }

        return EscalonamientoPeriodo::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();
    }
}
