<?php

namespace App\Services\Solicitudes;

use App\Models\CatalogoListaDescuento;
use Illuminate\Support\Collection;

class EscalonamientoService
{
    public function obtenerPorcentajeLista(?CatalogoListaDescuento $lista): float
    {
        if (!$lista) {
            return 0.0;
        }

        if ($lista->porcentaje_descuento !== null) {
            return (float) $lista->porcentaje_descuento;
        }

        $pct = $lista->relationLoaded('porcentajeEscalonamiento')
            ? $lista->porcentajeEscalonamiento
            : $lista->porcentajeEscalonamiento()->first();

        if (!$pct || !$pct->activo) {
            return 0.0;
        }

        return (float) $pct->porcentaje_descuento;
    }

    public function calcularMontoFinalTentativo(float $montoCotizado, float $porcentajeDescuento): float
    {
        return round($montoCotizado * (1 - ($porcentajeDescuento / 100)), 2);
    }

    public function calcularMontoBrutoNecesario(float $faltanteNeto, float $porcentajeDescuento): float
    {
        if ($faltanteNeto <= 0) {
            return 0.0;
        }

        $multiplicador = 1 - ($porcentajeDescuento / 100);

        if ($multiplicador <= 0) {
            return round($faltanteNeto, 2);
        }

        return round($faltanteNeto / $multiplicador, 2);
    }

    public function umbralEfectivo(CatalogoListaDescuento $lista): float
    {
        return $this->calcularMontoBrutoNecesario(
            (float) $lista->monto_requerido,
            $this->obtenerPorcentajeLista($lista)
        );
    }

    /**
     * Lista más alta cuyo umbral efectivo (monto_requerido / (1 − %)) es ≤ $monto.
     *
     * @param  Collection<int, CatalogoListaDescuento>|array  $catalogoListas
     */
    public function resolverListaPorMonto(float $monto, Collection|array $catalogoListas): ?CatalogoListaDescuento
    {
        $listasValidas = $this->filtrarListasValidas($catalogoListas);

        return $listasValidas->first(fn ($l) => $monto >= $this->umbralEfectivo($l));
    }

    /**
     * @param  Collection<int, CatalogoListaDescuento>|array  $catalogoListas
     */
    public function resolverListaPorMontoId(float $monto, Collection|array $catalogoListas): int
    {
        $lista = $this->resolverListaPorMonto($monto, $catalogoListas);
        if ($lista) {
            return (int) $lista->id;
        }

        $pg = collect($catalogoListas)->first(
            fn ($l) => strtoupper((string) $l->nombre) === 'PUBLICO GENERAL'
        );

        if ($pg) {
            return (int) $pg->id;
        }

        return (int) (CatalogoListaDescuento::where('nombre', 'PUBLICO GENERAL')->value('id') ?? 1);
    }

    /**
     * @param  Collection<int, CatalogoListaDescuento>|array  $catalogoListas
     */
    public function evaluar(
        float $montoHistorico,
        float $montoCotizado,
        ?int $listaSolicitadaId,
        Collection|array $catalogoListas,
        ?float $requisitoListaActual = null
    ): array {
        $listas = collect($catalogoListas);
        $listasValidas = $this->filtrarListasValidas($listas);

        $totalProyectadoBruto = round($montoHistorico + $montoCotizado, 2);

        $listaCalificadaBruto = $this->buscarListaCalificadaPorCatalogo($listasValidas, $totalProyectadoBruto);
        $listaCalificadaEfectiva = $this->resolverListaPorMonto($totalProyectadoBruto, $listasValidas);

        $requisitoActual = $requisitoListaActual ?? 0;
        $esAscenso = $listaCalificadaEfectiva
            && (float) $listaCalificadaEfectiva->monto_requerido > $requisitoActual;

        $listaAnticipada = $listaCalificadaEfectiva;
        $porcentajeDescuento = $this->obtenerPorcentajeLista($listaAnticipada);
        $umbralEfectivoAnticipada = $listaAnticipada ? $this->umbralEfectivo($listaAnticipada) : 0.0;

        $montoFinalTentativo = $this->calcularMontoFinalTentativo($montoCotizado, $porcentajeDescuento);
        $totalProyectadoNeto = round($montoHistorico + $montoFinalTentativo, 2);

        $listaCalificadaNeto = $this->resolverListaPorMonto($totalProyectadoNeto, $listasValidas);

        $listaSiguienteEfectiva = $this->buscarListaSiguientePorUmbralEfectivo($listasValidas, $totalProyectadoBruto);
        $porcentajeListaSiguiente = $listaSiguienteEfectiva
            ? $this->obtenerPorcentajeLista($listaSiguienteEfectiva)
            : $porcentajeDescuento;

        $umbralEfectivoSiguiente = $listaSiguienteEfectiva
            ? $this->umbralEfectivo($listaSiguienteEfectiva)
            : 0.0;

        $faltanteBrutoParaSiguiente = $listaSiguienteEfectiva
            ? max(0, round($umbralEfectivoSiguiente - $totalProyectadoBruto, 2))
            : 0.0;

        $faltanteNetoSiguiente = $listaSiguienteEfectiva
            ? max(0, round((float) $listaSiguienteEfectiva->monto_requerido - $totalProyectadoNeto, 2))
            : 0.0;

        $montoBrutoParaSiguiente = $faltanteBrutoParaSiguiente;

        $casiAlcanzaSiguiente = false;
        $listaCasiAlcanzada = null;
        $faltanteBrutoCasi = 0.0;
        $umbralEfectivoCasi = 0.0;

        if ($listaCalificadaBruto
            && (!$listaCalificadaEfectiva
                || (float) $listaCalificadaBruto->monto_requerido > (float) $listaCalificadaEfectiva->monto_requerido)
        ) {
            $casiAlcanzaSiguiente = true;
            $listaCasiAlcanzada = $listaCalificadaBruto;
            $umbralEfectivoCasi = $this->umbralEfectivo($listaCalificadaBruto);
            $faltanteBrutoCasi = max(0, round($umbralEfectivoCasi - $totalProyectadoBruto, 2));
        }

        $mantieneListaAnticipada = $listaAnticipada
            ? $totalProyectadoBruto >= $umbralEfectivoAnticipada
            : true;

        $listaSolicitadaIdEfectivo = $listaSolicitadaId
            ?: ($esAscenso && $listaCalificadaEfectiva ? (int) $listaCalificadaEfectiva->id : null);

        $desgloseListas = $listasValidas
            ->sortBy(fn ($l) => (float) $l->monto_requerido)
            ->map(fn ($l) => [
                'id' => $l->id,
                'nombre' => $l->nombre,
                'monto_requerido' => (float) $l->monto_requerido,
                'umbral_efectivo' => $this->umbralEfectivo($l),
                'cubre' => $totalProyectadoBruto >= $this->umbralEfectivo($l),
            ])
            ->values()
            ->all();

        return [
            'monto_historico' => round($montoHistorico, 2),
            'monto_cotizado' => round($montoCotizado, 2),
            'porcentaje_descuento' => $porcentajeDescuento,
            'porcentaje_siguiente' => $porcentajeListaSiguiente,
            'monto_final_tentativo' => $montoFinalTentativo,
            'total_proyectado_bruto' => $totalProyectadoBruto,
            'total_proyectado_neto' => $totalProyectadoNeto,
            'lista_calificada_bruto' => $listaCalificadaBruto,
            'lista_calificada_efectiva' => $listaCalificadaEfectiva,
            'lista_calificada_neto' => $listaCalificadaNeto,
            'lista_anticipada' => $listaAnticipada,
            'lista_solicitada_id_efectivo' => $listaSolicitadaIdEfectivo,
            'lista_siguiente_bruto' => $listaSiguienteEfectiva,
            'lista_siguiente_neto' => $listaSiguienteEfectiva,
            'lista_siguiente_efectiva' => $listaSiguienteEfectiva,
            'faltante_neto_siguiente' => $faltanteNetoSiguiente,
            'faltante_bruto_para_siguiente' => $faltanteBrutoParaSiguiente,
            'monto_bruto_para_siguiente' => $montoBrutoParaSiguiente,
            'umbral_efectivo_anticipada' => $umbralEfectivoAnticipada,
            'umbral_efectivo_siguiente' => $umbralEfectivoSiguiente,
            'mantiene_lista_anticipada' => $mantieneListaAnticipada,
            'mantiene_lista_solicitada' => $mantieneListaAnticipada,
            'faltante_neto_mantener' => 0.0,
            'monto_bruto_para_mantener' => $faltanteBrutoCasi,
            'casi_alcanza_siguiente' => $casiAlcanzaSiguiente,
            'lista_casi_alcanzada' => $listaCasiAlcanzada,
            'faltante_bruto_casi' => $faltanteBrutoCasi,
            'umbral_efectivo_casi' => $umbralEfectivoCasi,
            // Compat: mismo significado que casi_alcanza_siguiente
            'bruto_califica_neto_no' => $casiAlcanzaSiguiente,
            'es_ascenso' => $esAscenso,
            'desglose_listas' => $desgloseListas,
        ];
    }

    /**
     * @param  Collection<int, CatalogoListaDescuento>|array  $catalogoListas
     * @return Collection<int, CatalogoListaDescuento>
     */
    public function filtrarListasValidas(Collection|array $catalogoListas): Collection
    {
        return collect($catalogoListas)
            ->filter(fn ($l) => !str_contains(strtoupper($l->nombre), 'COLABORADOR')
                && !str_contains(strtoupper($l->nombre), 'PLATAFORMAS'))
            ->sortByDesc(fn ($l) => (float) $l->monto_requerido)
            ->values();
    }

    private function buscarListaCalificadaPorCatalogo(Collection $listasValidas, float $total): ?CatalogoListaDescuento
    {
        return $listasValidas->first(fn ($l) => $total >= (float) $l->monto_requerido);
    }

    private function buscarListaSiguientePorUmbralEfectivo(Collection $listasValidas, float $total): ?CatalogoListaDescuento
    {
        return $listasValidas
            ->sortBy(fn ($l) => $this->umbralEfectivo($l))
            ->first(fn ($l) => $this->umbralEfectivo($l) > $total);
    }
}
