<?php

namespace App\Services\Solicitudes;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Services\Escalonamiento\ProyeccionClienteEscalonamiento;
use Illuminate\Support\Collection;

class EscalonamientoService
{
    public function __construct(
        private ?ProyeccionClienteEscalonamiento $proyeccionCliente = null,
    ) {}
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

    /**
     * Descuento de la compra nueva: lo que falta para llegar al porcentaje de la lista destino
     * respecto al porcentaje que el cliente ya tiene. Ambos salen del catálogo de listas.
     */
    public function porcentajeAplicado(?CatalogoListaDescuento $listaDestino, ?CatalogoListaDescuento $listaActual): float
    {
        return max(0, round(
            $this->obtenerPorcentajeLista($listaDestino) - $this->obtenerPorcentajeLista($listaActual),
            2
        ));
    }

    /**
     * @param  Collection<int, CatalogoListaDescuento>|array  $catalogoListas
     */
    public function evaluarCompraCliente(
        Cliente $cliente,
        float $montoCotizado,
        ?int $listaSolicitadaId,
        Collection|array $catalogoListas
    ): array {
        $listas = collect($catalogoListas);
        if ($this->proyeccionCliente) {
            $listaActual = $this->proyeccionCliente->listaActualEnCatalogo($cliente, $listas);
            $acumulado = $this->proyeccionCliente->montoAcumulado($cliente);
        } else {
            $listaActual = $cliente->lista_actual_id
                ? $listas->firstWhere('id', (int) $cliente->lista_actual_id)
                : null;
            $acumulado = (float) ($cliente->monto_venta_actual ?? 0);
        }

        return $this->evaluar(
            $acumulado,
            $montoCotizado,
            $listaSolicitadaId,
            $listas,
            $listaActual ? (float) $listaActual->monto_requerido : 0.0,
            $listaActual
        );
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
     * Cotización ya valuada en la lista actual: solo se descuenta el porcentaje que falta
     * para la lista destino. Sin lista actual equivale al umbral de seguridad completo.
     */
    public function umbralIncremental(CatalogoListaDescuento $listaDestino, ?CatalogoListaDescuento $listaActual = null): float
    {
        return $this->calcularMontoBrutoNecesario(
            (float) $listaDestino->monto_requerido,
            $this->porcentajeAplicado($listaDestino, $listaActual)
        );
    }

    /**
     * Lista más alta cuyo umbral bruto efectivo (monto_requerido / (1 − %)) es ≤ $monto acumulado bruto.
     * Solo para cotización / recomendación bruta; no usar con pagos netos confirmados.
     *
     * @param  Collection<int, CatalogoListaDescuento>|array  $catalogoListas
     */
    public function resolverListaPorMontoBrutoAcumulado(
        float $monto,
        Collection|array $catalogoListas,
        ?CatalogoListaDescuento $listaActual = null
    ): ?CatalogoListaDescuento {
        $listasValidas = $this->filtrarListasValidas($catalogoListas);

        return $listasValidas->first(fn ($l) => $monto >= $this->umbralIncremental($l, $listaActual));
    }

    /**
     * @deprecated Use resolverListaPorMontoBrutoAcumulado or resolverListaPorAcumuladoNeto según el significado del monto.
     *
     * @param  Collection<int, CatalogoListaDescuento>|array  $catalogoListas
     */
    public function resolverListaPorMonto(float $monto, Collection|array $catalogoListas): ?CatalogoListaDescuento
    {
        return $this->resolverListaPorMontoBrutoAcumulado($monto, $catalogoListas);
    }

    /**
     * Lista más alta cuyo monto_requerido (neto) es ≤ $acumuladoPagado.
     *
     * @param  Collection<int, CatalogoListaDescuento>|array  $catalogoListas
     */
    public function resolverListaPorAcumuladoNeto(float $acumuladoPagado, Collection|array $catalogoListas): ?CatalogoListaDescuento
    {
        return $this->buscarListaCalificadaPorCatalogo(
            $this->filtrarListasValidas($catalogoListas),
            round($acumuladoPagado, 2)
        );
    }

    /**
     * @param  Collection<int, CatalogoListaDescuento>|array  $catalogoListas
     */
    public function resolverListaPorAcumuladoNetoId(float $acumuladoPagado, Collection|array $catalogoListas): int
    {
        $lista = $this->resolverListaPorAcumuladoNeto($acumuladoPagado, $catalogoListas);
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
    public function resolverListaPorMontoId(float $monto, Collection|array $catalogoListas): int
    {
        $lista = $this->resolverListaPorMontoBrutoAcumulado($monto, $catalogoListas);
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
        ?float $requisitoListaActual = null,
        ?CatalogoListaDescuento $listaActual = null
    ): array {
        $listas = collect($catalogoListas);
        $listasValidas = $this->filtrarListasValidas($listas);

        $requisitoActual = $requisitoListaActual ?? 0;
        if (! $listaActual) {
            $listaActual = collect($catalogoListas)->first(
                fn ($l) => abs((float) $l->monto_requerido - (float) $requisitoActual) < 0.009
            );
        }

        $totalProyectadoBruto = round($montoHistorico + $montoCotizado, 2);

        $listaCalificadaBruto = $this->buscarListaCalificadaPorCatalogo($listasValidas, $totalProyectadoBruto);
        // El acumulado ya pagado es neto. Solo la cotización nueva lleva el descuento
        // adicional, así que no se compara contra monto_requerido / (1 − %).
        $listaCalificadaEfectiva = $listasValidas->first(
            fn ($l) => $this->calificaPorNetoDeLista($l, $montoHistorico, $montoCotizado, $listaActual)
        );

        $listaProvisional = $listaCalificadaBruto;
        $esAscenso = $listaProvisional
            && (float) $listaProvisional->monto_requerido > $requisitoActual;

        $listaParaDescuento = $listaSolicitadaId
            ? $listas->firstWhere('id', $listaSolicitadaId)
            : null;
        if (! $listaParaDescuento && $esAscenso && $listaProvisional) {
            $listaParaDescuento = $listaProvisional;
        }
        if (! $listaParaDescuento) {
            $listaParaDescuento = $listaCalificadaEfectiva;
        }

        $listaAnticipada = $listaProvisional ?: $listaCalificadaEfectiva;
        $porcentajeDescuento = $this->porcentajeAplicado($listaParaDescuento, $listaActual);
        $umbralEfectivoAnticipada = $listaAnticipada ? $this->umbralIncremental($listaAnticipada, $listaActual) : 0.0;

        $montoFinalTentativo = $this->calcularMontoFinalTentativo($montoCotizado, $porcentajeDescuento);
        $totalProyectadoNeto = round($montoHistorico + $montoFinalTentativo, 2);

        $listaCalificadaNeto = $this->resolverListaPorAcumuladoNeto($totalProyectadoNeto, $listasValidas);

        $listaSiguienteEfectiva = $this->buscarListaSiguientePorUmbralEfectivo($listasValidas, $totalProyectadoBruto, $listaActual);
        $porcentajeListaSiguiente = $listaSiguienteEfectiva
            ? $this->obtenerPorcentajeLista($listaSiguienteEfectiva)
            : $porcentajeDescuento;

        $umbralEfectivoSiguiente = $listaSiguienteEfectiva
            ? $this->umbralIncremental($listaSiguienteEfectiva, $listaActual)
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

        $faltanteNetoCasi = 0.0;
        if ($listaProvisional
            && $listaCalificadaNeto
            && (float) $listaProvisional->monto_requerido > (float) $listaCalificadaNeto->monto_requerido
        ) {
            $casiAlcanzaSiguiente = true;
            $listaCasiAlcanzada = $listaProvisional;
            $umbralEfectivoCasi = $this->umbralIncremental($listaProvisional, $listaActual);
            $faltanteNetoCasi = max(0, round((float) $listaProvisional->monto_requerido - $totalProyectadoNeto, 2));
            $faltanteBrutoCasi = $this->calcularMontoBrutoNecesario(
                $faltanteNetoCasi,
                $this->porcentajeAplicado($listaProvisional, $listaActual)
            );
        }

        $mantieneListaAnticipada = $listaProvisional
            ? $totalProyectadoNeto >= (float) $listaProvisional->monto_requerido
            : true;

        $listaSolicitadaIdEfectivo = $listaSolicitadaId
            ?: ($esAscenso && $listaProvisional ? (int) $listaProvisional->id : null);

        $desgloseListas = $listasValidas
            ->sortBy(fn ($l) => (float) $l->monto_requerido)
            ->map(fn ($l) => [
                'id' => $l->id,
                'nombre' => $l->nombre,
                'monto_requerido' => (float) $l->monto_requerido,
                'umbral_efectivo' => $this->umbralIncremental($l, $listaActual),
                'cubre' => $this->calificaPorNetoDeLista($l, $montoHistorico, $montoCotizado, $listaActual),
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
            'faltante_neto_casi' => $faltanteNetoCasi,
            'umbral_efectivo_casi' => $umbralEfectivoCasi,
            'lista_provisional' => $listaProvisional,
            'lista_confirmacion_estimada' => $listaCalificadaNeto,
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
            ->filter(fn ($l) => $this->visibleEnSolicitudes($l))
            ->sortByDesc(fn ($l) => (float) $l->monto_requerido)
            ->values();
    }

    private function calificaPorNetoDeLista(
        CatalogoListaDescuento $lista,
        float $montoHistorico,
        float $montoCotizado,
        ?CatalogoListaDescuento $listaActual
    ): bool {
        $neto = round(
            $montoHistorico + $this->calcularMontoFinalTentativo(
                $montoCotizado,
                $this->porcentajeAplicado($lista, $listaActual)
            ),
            2
        );

        return $neto >= (float) $lista->monto_requerido;
    }

    private function visibleEnSolicitudes(CatalogoListaDescuento $lista): bool
    {
        $nombre = strtoupper((string) $lista->nombre);
        if (str_contains($nombre, 'COLABORADOR') || str_contains($nombre, 'PLATAFORMAS')) {
            return false;
        }

        $visible = $lista->visible_en_solicitudes;

        return $visible !== false && $visible !== 0 && $visible !== '0';
    }

    private function buscarListaCalificadaPorCatalogo(Collection $listasValidas, float $total): ?CatalogoListaDescuento
    {
        return $listasValidas->first(fn ($l) => $total >= (float) $l->monto_requerido);
    }

    private function buscarListaSiguientePorUmbralEfectivo(
        Collection $listasValidas,
        float $total,
        ?CatalogoListaDescuento $listaActual
    ): ?CatalogoListaDescuento {
        return $listasValidas
            ->sortBy(fn ($l) => $this->umbralIncremental($l, $listaActual))
            ->first(fn ($l) => $this->umbralIncremental($l, $listaActual) > $total);
    }
}
