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

        $listasValidas = $listas
            ->filter(fn ($l) => !str_contains(strtoupper($l->nombre), 'COLABORADOR')
                && !str_contains(strtoupper($l->nombre), 'PLATAFORMAS'))
            ->sortByDesc(fn ($l) => (float) $l->monto_requerido)
            ->values();

        $totalProyectadoBruto = round($montoHistorico + $montoCotizado, 2);

        $listaCalificadaBruto = $this->buscarListaCalificada($listasValidas, $totalProyectadoBruto);

        $requisitoActual = $requisitoListaActual ?? 0;
        $esAscenso = $listaCalificadaBruto
            && (float) $listaCalificadaBruto->monto_requerido > $requisitoActual;

        // Anticipación: descuento según la lista que alcanza el bruto (independiente de la lista actual del cliente)
        $listaAnticipada = $listaCalificadaBruto;

        $porcentajeDescuento = $this->obtenerPorcentajeLista($listaAnticipada);

        $montoFinalTentativo = $this->calcularMontoFinalTentativo($montoCotizado, $porcentajeDescuento);
        $totalProyectadoNeto = round($montoHistorico + $montoFinalTentativo, 2);

        $listaCalificadaNeto = $this->buscarListaCalificada($listasValidas, $totalProyectadoNeto);

        $listaSiguienteBruto = $this->buscarListaSiguiente($listasValidas, $totalProyectadoBruto);
        $listaSiguienteNeto = $this->buscarListaSiguiente($listasValidas, $totalProyectadoNeto);

        $faltanteNetoSiguiente = $listaSiguienteNeto
            ? max(0, round((float) $listaSiguienteNeto->monto_requerido - $totalProyectadoNeto, 2))
            : 0.0;

        $porcentajeListaSiguiente = $listaSiguienteNeto
            ? $this->obtenerPorcentajeLista($listas->first(fn ($l) => (int) $l->id === (int) $listaSiguienteNeto->id))
            : $porcentajeDescuento;

        $montoBrutoParaSiguiente = $this->calcularMontoBrutoNecesario(
            $faltanteNetoSiguiente,
            $porcentajeListaSiguiente
        );

        $mantieneListaAnticipada = true;
        $faltanteNetoMantener = 0.0;
        $montoBrutoParaMantener = 0.0;
        $brutoCalificaNetoNo = false;

        if ($listaAnticipada) {
            $umbralAnticipado = (float) $listaAnticipada->monto_requerido;
            $mantieneListaAnticipada = $totalProyectadoNeto >= $umbralAnticipado;
            $faltanteNetoMantener = max(0, round($umbralAnticipado - $totalProyectadoNeto, 2));
            $montoBrutoParaMantener = $this->calcularMontoBrutoNecesario(
                $faltanteNetoMantener,
                $porcentajeDescuento
            );
            $brutoCalificaNetoNo = $totalProyectadoBruto >= $umbralAnticipado && !$mantieneListaAnticipada;
        }

        $listaSolicitadaIdEfectivo = $listaSolicitadaId
            ?: ($esAscenso && $listaCalificadaBruto ? (int) $listaCalificadaBruto->id : null);

        $desgloseListas = $listasValidas
            ->sortBy(fn ($l) => (float) $l->monto_requerido)
            ->map(fn ($l) => [
                'id' => $l->id,
                'nombre' => $l->nombre,
                'monto_requerido' => (float) $l->monto_requerido,
                'cubre' => $totalProyectadoNeto >= (float) $l->monto_requerido,
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
            'lista_calificada_neto' => $listaCalificadaNeto,
            'lista_anticipada' => $listaAnticipada,
            'lista_solicitada_id_efectivo' => $listaSolicitadaIdEfectivo,
            'lista_siguiente_bruto' => $listaSiguienteBruto,
            'lista_siguiente_neto' => $listaSiguienteNeto,
            'faltante_neto_siguiente' => $faltanteNetoSiguiente,
            'monto_bruto_para_siguiente' => $montoBrutoParaSiguiente,
            'mantiene_lista_anticipada' => $mantieneListaAnticipada,
            'mantiene_lista_solicitada' => $mantieneListaAnticipada,
            'faltante_neto_mantener' => $faltanteNetoMantener,
            'monto_bruto_para_mantener' => $montoBrutoParaMantener,
            'bruto_califica_neto_no' => $brutoCalificaNetoNo,
            'es_ascenso' => $esAscenso,
            'desglose_listas' => $desgloseListas,
        ];
    }

    private function buscarListaCalificada(Collection $listasValidas, float $total): ?CatalogoListaDescuento
    {
        return $listasValidas->first(fn ($l) => $total >= (float) $l->monto_requerido);
    }

    private function buscarListaSiguiente(Collection $listasValidas, float $total): ?CatalogoListaDescuento
    {
        return $listasValidas
            ->sortBy(fn ($l) => (float) $l->monto_requerido)
            ->first(fn ($l) => (float) $l->monto_requerido > $total);
    }
}
