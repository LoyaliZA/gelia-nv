<?php

namespace App\Services\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Services\Solicitudes\EscalonamientoService;

class EvaluarListaClienteEscalonamiento
{
    public function __construct(
        private EscalonamientoService $clasificador,
        private ListasPeriodoEscalonamiento $listasPeriodo,
        private ParticipacionClienteEscalonamiento $participacion,
        private SincronizarIncidenciaDivergenciaLista $divergenciaLista,
        private ListaPublicoGeneralEscalonamiento $listaPublicoGeneral,
    ) {}

    public function clasificarAcumulado(EscalonamientoPeriodo $periodo, string $acumulado): ?int
    {
        $listas = $this->listasPeriodo->listasParticipantes($periodo);
        if ($listas->isEmpty()) {
            return null;
        }

        $lista = $this->clasificador->resolverListaPorAcumuladoNeto((float) $acumulado, $listas);

        return $lista?->id;
    }

    public function clasificarAcumuladoParaRenovacion(EscalonamientoPeriodo $periodo, string $acumulado): ?int
    {
        $id = $this->clasificarAcumulado($periodo, $acumulado);
        if ($id !== null) {
            return $id;
        }

        try {
            return $this->listaPublicoGeneral->id();
        } catch (\Illuminate\Validation\ValidationException) {
            return null;
        }
    }

    public function listaBaseInicial(EscalonamientoPeriodo $periodo, Cliente $cliente): ?int
    {
        if (! $this->participacion->clienteParticipa($periodo, $cliente)) {
            return null;
        }

        $listaId = (int) ($cliente->lista_actual_id ?? 0);

        return $listaId > 0 ? $listaId : null;
    }

    public function listaVigenteId(EscalonamientoPeriodo $periodo, ?int $listaBaseId, ?int $clasificacionMesId): ?int
    {
        if (! $listaBaseId && ! $clasificacionMesId) {
            return null;
        }

        return $this->listasPeriodo->mejorListaId($periodo, $listaBaseId, $clasificacionMesId);
    }

    /**
     * @return array{
     *     lista_siguiente_propuesta_id: ?int,
     *     cumple_mantenimiento: bool,
     *     faltante_mantenimiento: ?string
     * }
     */
    public function evaluarRenovacion(
        EscalonamientoPeriodo $periodo,
        ?int $listaVigenteId,
        ?int $clasificacionMesId,
        string $acumulado,
    ): array {
        if (! $listaVigenteId) {
            return [
                'lista_siguiente_propuesta_id' => $clasificacionMesId,
                'cumple_mantenimiento' => false,
                'faltante_mantenimiento' => null,
            ];
        }

        $requisito = $this->listasPeriodo->montoRequerido($periodo, $listaVigenteId);
        if ($requisito === null) {
            return [
                'lista_siguiente_propuesta_id' => $listaVigenteId,
                'cumple_mantenimiento' => true,
                'faltante_mantenimiento' => '0.00',
            ];
        }

        $cumple = bccomp($acumulado, $requisito, 2) >= 0;
        $faltante = $cumple ? '0.00' : bcsub($requisito, $acumulado, 2);

        return [
            'lista_siguiente_propuesta_id' => $cumple
                ? $listaVigenteId
                : $clasificacionMesId,
            'cumple_mantenimiento' => $cumple,
            'faltante_mantenimiento' => $faltante,
        ];
    }

    public function crearResumen(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        string $acumulado,
    ): EscalonamientoResumenCliente {
        $clasificacionId = $this->clasificarAcumulado($periodo, $acumulado);
        $listaBaseId = $this->listaBaseInicial($periodo, $cliente);
        $listaVigenteId = $this->listaVigenteId($periodo, $listaBaseId, $clasificacionId);

        $resumen = EscalonamientoResumenCliente::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $cliente->id,
            'acumulado' => $acumulado,
            'lista_base_id' => $listaBaseId,
            'clasificacion_mes_id' => $clasificacionId,
            'clasificacion_mes_max_id' => $clasificacionId,
            'lista_vigente_id' => $listaVigenteId,
        ]);

        $this->divergenciaLista->sincronizar($periodo, $cliente, $resumen);

        return $resumen;
    }

    public function sincronizarResumen(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        EscalonamientoResumenCliente $resumen,
        string $acumulado,
    ): void {
        $clasificacionId = $this->clasificarAcumulado($periodo, $acumulado);
        $maxId = $this->listasPeriodo->mejorListaId(
            $periodo,
            $resumen->clasificacion_mes_max_id,
            $clasificacionId,
        );

        if (! $resumen->lista_base_id) {
            $resumen->lista_base_id = $this->listaBaseInicial($periodo, $cliente);
        }

        $resumen->acumulado = $acumulado;
        $resumen->clasificacion_mes_id = $clasificacionId;
        $resumen->clasificacion_mes_max_id = $maxId;
        $resumen->lista_vigente_id = $this->listaVigenteId(
            $periodo,
            $resumen->lista_base_id,
            $clasificacionId,
        );

        $this->divergenciaLista->sincronizar($periodo, $cliente, $resumen);
    }

    /**
     * @return array<string, mixed>
     */
    public function proyeccionLectura(
        EscalonamientoPeriodo $periodo,
        Cliente $cliente,
        ?EscalonamientoResumenCliente $resumen,
        ?string $acumuladoSinResumen = null,
    ): array {
        $acumulado = $resumen ? (string) $resumen->acumulado : ($acumuladoSinResumen ?? '0.00');
        $clasificacionId = $resumen?->clasificacion_mes_id;
        if ($clasificacionId === null) {
            $clasificacionId = $this->clasificarAcumuladoParaRenovacion($periodo, $acumulado);
        }
        $listaBaseId = $resumen?->lista_base_id ?? $this->listaBaseInicial($periodo, $cliente);
        $listaVigenteId = $resumen?->lista_vigente_id
            ?? $this->listaVigenteId($periodo, $listaBaseId, $clasificacionId);

        $renovacion = $this->evaluarRenovacion($periodo, $listaVigenteId, $clasificacionId, $acumulado);

        return [
            'lista_base_id' => $listaBaseId,
            'lista_vigente_id' => $listaVigenteId,
            'clasificacion_mes_id' => $clasificacionId,
            'clasificacion_mes_max_id' => $resumen?->clasificacion_mes_max_id,
            'lista_siguiente_propuesta_id' => $renovacion['lista_siguiente_propuesta_id'],
            'cumple_mantenimiento' => $renovacion['cumple_mantenimiento'],
            'faltante_mantenimiento' => $renovacion['faltante_mantenimiento'],
            'participa' => $this->participacion->clienteParticipa($periodo, $cliente),
        ];
    }

    public function nombreLista(?int $listaId): ?string
    {
        if (! $listaId) {
            return null;
        }

        return CatalogoListaDescuento::query()->whereKey($listaId)->value('nombre');
    }
}
