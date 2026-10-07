<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Models\Escalonamiento\EscalonamientoCierreDetalle;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;

class AbrirPeriodoSiguienteEscalonamiento
{
    public function __construct(
        private AbrirPeriodoEscalonamiento $abrirPeriodo,
    ) {}

    public function abrirDesdeCierre(EscalonamientoCierre $cierre, bool $reconstruccion = false): EscalonamientoPeriodo
    {
        $periodoCerrado = $cierre->periodo;
        $anio = (int) $periodoCerrado->anio;
        $mes = (int) $periodoCerrado->mes;
        if ($mes === 12) {
            $anio++;
            $mes = 1;
        } else {
            $mes++;
        }

        if ($reconstruccion) {
            return $this->sembrarListaBase($cierre, $anio, $mes);
        }

        $nuevo = $this->abrirPeriodo->abrir($anio, $mes);

        $detalles = EscalonamientoCierreDetalle::query()
            ->where('escalonamiento_cierre_id', $cierre->id)
            ->get();

        foreach ($detalles as $detalle) {
            $listaBase = $detalle->lista_siguiente_id;
            if (! $listaBase) {
                $listaBase = Cliente::query()->whereKey($detalle->cliente_id)->value('lista_actual_id');
            }
            if (! $listaBase) {
                continue;
            }

            EscalonamientoResumenCliente::query()->updateOrCreate(
                [
                    'escalonamiento_periodo_id' => $nuevo->id,
                    'cliente_id' => $detalle->cliente_id,
                ],
                [
                    'acumulado' => 0,
                    'lista_base_id' => $listaBase,
                    'lista_vigente_id' => $listaBase,
                    'clasificacion_mes_id' => null,
                    'clasificacion_mes_max_id' => null,
                ],
            );
        }

        return $nuevo;
    }

    /**
     * Deja lista_base en el mes siguiente sin abrir un período operativo ni borrar su acumulado.
     */
    private function sembrarListaBase(EscalonamientoCierre $cierre, int $anio, int $mes): EscalonamientoPeriodo
    {
        $destino = EscalonamientoPeriodo::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->lockForUpdate()
            ->first();

        if (! $destino) {
            $destino = $this->abrirPeriodo->abrir($anio, $mes);
            if ($destino->estado === EscalonamientoPeriodo::ESTADO_ABIERTO) {
                $destino->estado = EscalonamientoPeriodo::ESTADO_HISTORIAL;
                $destino->save();
            }
        }

        $detalles = EscalonamientoCierreDetalle::query()
            ->where('escalonamiento_cierre_id', $cierre->id)
            ->get();

        foreach ($detalles as $detalle) {
            $listaBase = $detalle->lista_siguiente_id;
            if (! $listaBase) {
                $listaBase = Cliente::query()->whereKey($detalle->cliente_id)->value('lista_actual_id');
            }
            if (! $listaBase) {
                continue;
            }

            $resumen = EscalonamientoResumenCliente::query()
                ->where('escalonamiento_periodo_id', $destino->id)
                ->where('cliente_id', $detalle->cliente_id)
                ->first();

            if ($resumen) {
                $resumen->lista_base_id = $listaBase;
                if (! $resumen->lista_vigente_id) {
                    $resumen->lista_vigente_id = $listaBase;
                }
                $resumen->save();

                continue;
            }

            EscalonamientoResumenCliente::query()->create([
                'escalonamiento_periodo_id' => $destino->id,
                'cliente_id' => $detalle->cliente_id,
                'acumulado' => 0,
                'lista_base_id' => $listaBase,
                'lista_vigente_id' => $listaBase,
                'clasificacion_mes_id' => null,
                'clasificacion_mes_max_id' => null,
            ]);
        }

        return $destino;
    }
}
