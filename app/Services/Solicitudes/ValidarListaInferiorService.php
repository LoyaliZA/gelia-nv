<?php

namespace App\Services\Solicitudes;

use App\Models\CatalogoListaDescuento;
use App\Models\SolicitudTag;

class ValidarListaInferiorService
{
    public function esProcesoCambioLista(SolicitudTag $solicitud): bool
    {
        $nombre = strtoupper($solicitud->proceso?->nombre ?? '');

        return str_contains($nombre, 'LISTA');
    }

    public function obtenerListaReferencia(SolicitudTag $solicitud): ?CatalogoListaDescuento
    {
        $solicitud->loadMissing(['cliente.listaDescuento', 'listaDescuento']);

        $candidatas = collect([
            $solicitud->cliente?->listaDescuento,
            $solicitud->listaDescuento,
        ])->filter();

        if ($candidatas->isEmpty()) {
            return null;
        }

        return $candidatas->sortByDesc(fn ($lista) => (float) $lista->monto_requerido)->first();
    }

    /**
     * @return CatalogoListaDescuento[]
     */
    public function listasInferioresDisponibles(SolicitudTag $solicitud): array
    {
        $referencia = $this->obtenerListaReferencia($solicitud);

        if (!$referencia) {
            return [];
        }

        $montoRef = (float) $referencia->monto_requerido;

        return CatalogoListaDescuento::where('activo', true)
            ->where('nombre', 'not like', '%COLABORADOR%')
            ->where('nombre', 'not like', '%PLATAFORMAS%')
            ->where('monto_requerido', '<', $montoRef)
            ->orderByDesc('monto_requerido')
            ->get()
            ->all();
    }

    public function validarListaInferior(SolicitudTag $solicitud, int $listaRebajaId): CatalogoListaDescuento
    {
        $referencia = $this->obtenerListaReferencia($solicitud);

        if (!$referencia) {
            abort(422, 'No se puede determinar la lista de referencia para validar la rebaja.');
        }

        $listaRebaja = CatalogoListaDescuento::find($listaRebajaId);

        if (!$listaRebaja || !$listaRebaja->activo) {
            abort(422, 'La lista de rebaja seleccionada no es válida o está inactiva.');
        }

        $nombreUpper = strtoupper($listaRebaja->nombre);
        if (str_contains($nombreUpper, 'COLABORADOR') || str_contains($nombreUpper, 'PLATAFORMAS')) {
            abort(422, 'No se puede asignar una lista de colaborador o plataformas como rebaja.');
        }

        if ((float) $listaRebaja->monto_requerido >= (float) $referencia->monto_requerido) {
            abort(422, 'Solo se pueden seleccionar listas inferiores a la lista actual o solicitada.');
        }

        return $listaRebaja;
    }
}
