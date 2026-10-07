<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoCambioLista;
use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Models\Escalonamiento\EscalonamientoCierreDetalle;

class AplicarCambioLista
{
    public function aplicar(
        EscalonamientoCierre $cierre,
        EscalonamientoCierreDetalle $detalle,
    ): ?EscalonamientoCambioLista {
        $cliente = Cliente::query()->lockForUpdate()->findOrFail($detalle->cliente_id);
        $key = $cierre->id.'|'.$cliente->id;

        $existente = EscalonamientoCambioLista::query()->where('idempotency_key', $key)->first();
        if ($existente) {
            return $existente;
        }

        $listaAnterior = $cliente->lista_actual_id;
        $montoAnterior = (string) $cliente->monto_venta_actual;
        $listaNueva = $detalle->lista_siguiente_id;

        if ($detalle->aplica_cambio_lista && $listaNueva) {
            $cliente->lista_actual_id = $listaNueva;
        }

        $cliente->monto_venta_actual = 0;
        $cliente->escalonamiento_meses_sin_compra = (int) $detalle->meses_sin_compra;

        if ($detalle->propone_inactivo) {
            $cliente->es_inactivo = true;
        } elseif ($detalle->extras['tuvo_actividad_compra'] ?? false) {
            $cliente->es_inactivo = false;
            $cliente->escalonamiento_ultima_compra_periodo_id = $cierre->escalonamiento_periodo_id;
        }

        $cliente->save();

        return EscalonamientoCambioLista::create([
            'escalonamiento_cierre_id' => $cierre->id,
            'cliente_id' => $cliente->id,
            'lista_anterior_id' => $listaAnterior,
            'lista_nueva_id' => $detalle->aplica_cambio_lista && $listaNueva ? $listaNueva : $listaAnterior,
            'monto_anterior' => $montoAnterior,
            'motivo' => $detalle->motivo,
            'aplicado_en' => now(),
            'idempotency_key' => $key,
        ]);
    }
}
