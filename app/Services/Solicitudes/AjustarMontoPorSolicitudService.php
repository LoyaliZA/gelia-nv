<?php

namespace App\Services\Solicitudes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\SolicitudTag;
use App\Services\Clientes\ReactivarClienteInactivoService;
use App\Services\Escalonamiento\EscalonamientoAutoridad;
use App\Services\Escalonamiento\ProyeccionClienteEscalonamiento;

class AjustarMontoPorSolicitudService
{
    public function __construct(
        private EscalonamientoService $escalonamiento,
        private ReactivarClienteInactivoService $reactivarInactivo,
        private EscalonamientoAutoridad $autoridad,
        private ProyeccionClienteEscalonamiento $proyeccionCliente,
    ) {}

    /**
     * Aplica lista/tipo/tag provisionales. No suma al historial de venta.
     *
     * @return array{antes: array<string, mixed>, despues: array<string, mixed>}
     */
    public function aplicarBeneficios(SolicitudTag $solicitud): array
    {
        $cliente = $this->clienteDe($solicitud);
        if (! $cliente) {
            return [];
        }

        $antes = $this->capturarSnapshotCliente($cliente);

        if (
            $solicitud->catalogo_lista_descuento_id
            && ! $cliente->lista_bloqueada
            && ! $this->autoridad->clienteGobernadoPorModulo($cliente)
        ) {
            $cliente->lista_actual_id = $solicitud->catalogo_lista_descuento_id;
        }

        if ($solicitud->catalogo_tipo_cliente_id) {
            $cliente->catalogo_tipo_cliente_id = $solicitud->catalogo_tipo_cliente_id;
        }

        if ($solicitud->vendedor_id && $this->solicitudAsignaTag($solicitud)) {
            $cliente->vendedor_id = $solicitud->vendedor_id;
        }

        $cliente->save();
        $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);

        return [
            'antes' => $antes,
            'despues' => $this->capturarSnapshotCliente($cliente),
        ];
    }

    /**
     * Quita lista/tipo/tag provisionales y recalcula la lista con el monto guardado.
     * No modifica monto_venta_actual.
     *
     * @return array{antes: array<string, mixed>, despues: array<string, mixed>}
     */
    public function revertirBeneficios(SolicitudTag $solicitud, ?int $usuarioId = null, ?string $origen = null): array
    {
        $cliente = $this->clienteDe($solicitud);
        if (! $cliente) {
            return [];
        }

        $antes = $this->capturarSnapshotCliente($cliente);
        $this->recalcularListaCliente($cliente);

        if ($solicitud->catalogo_tipo_cliente_id) {
            $cliente->catalogo_tipo_cliente_id = null;
        }

        $cliente->save();
        $solicitud->monto_aplicado_al_cliente = 0;
        $solicitud->save();
        $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);

        return [
            'antes' => $antes,
            'despues' => $this->capturarSnapshotCliente($cliente),
        ];
    }

    /**
     * @return array{base: float, proyectado: float, aplicara_monto: bool}
     */
    public function proyectarMonto(Cliente $cliente, SolicitudTag $solicitud, float $montoFinal): array
    {
        $actual = $this->proyeccionCliente->montoAcumulado($cliente);

        if ($this->estaCubierta($solicitud)) {
            return [
                'base' => $actual,
                'proyectado' => $actual,
                'aplicara_monto' => false,
            ];
        }

        return [
            'base' => $actual,
            'proyectado' => $actual + $montoFinal,
            'aplicara_monto' => false,
        ];
    }

    /**
     * Registra el pago como auditoría y puede asignar la lista. No modifica monto_venta_actual.
     *
     * @return array{antes: array<string, mixed>, despues: array<string, mixed>}
     */
    public function aplicarPagoConfirmado(
        SolicitudTag $solicitud,
        float $montoFinal,
        ?int $usuarioId = null,
        bool $asignarListaSolicitada = false,
    ): array {
        $cliente = $this->clienteDe($solicitud);
        if (! $cliente) {
            return [];
        }

        $antes = $this->capturarSnapshotCliente($cliente);
        $montoGuardado = (float) $cliente->monto_venta_actual;

        $solicitud->monto_aplicado_al_cliente = $montoFinal;

        if (
            $asignarListaSolicitada
            && $solicitud->catalogo_lista_descuento_id
            && ! $cliente->lista_bloqueada
            && ! $this->autoridad->clienteGobernadoPorModulo($cliente)
        ) {
            $cliente->lista_actual_id = $solicitud->catalogo_lista_descuento_id;
        }

        $this->reactivarInactivo->ejecutar(
            $cliente,
            $montoGuardado,
            ! ($asignarListaSolicitada && $solicitud->catalogo_lista_descuento_id),
        );

        if ($cliente->es_inactivo && $asignarListaSolicitada && ! $cliente->lista_bloqueada) {
            $cliente->es_inactivo = false;
        }

        $cliente->save();
        $solicitud->save();
        $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);

        return [
            'antes' => $antes,
            'despues' => $this->capturarSnapshotCliente($cliente),
        ];
    }

    /**
     * Tras pisar el monto con Wizerp: no volver a sumar esa solicitud,
     * y no restar una capa que el CSV ya reemplazó.
     */
    public function marcarCubiertasPorCarga(Cliente $cliente, float $montoGeliaAntes, float $montoWizerp): int
    {
        $estados = array_values(array_filter([
            CatalogoEstadoSolicitud::idDe('Pendiente'),
            CatalogoEstadoSolicitud::idDe('Respondida'),
            CatalogoEstadoSolicitud::idDe('Verificada'),
        ]));

        if ($estados === []) {
            return 0;
        }

        $solicitudes = SolicitudTag::query()
            ->with('proceso')
            ->where('cliente_id', $cliente->id)
            ->where('pago_confirmado', false)
            ->whereIn('catalogo_estado_solicitud_id', $estados)
            ->get();

        $marcadas = 0;
        foreach ($solicitudes as $solicitud) {
            if ($solicitud->esProcesoOperativo()) {
                continue;
            }

            $capa = $this->capaPendiente($solicitud);
            $base = $montoGeliaAntes - $capa;
            $cotizado = (float) ($solicitud->monto_cotizado ?? 0);
            $cubierta = $cotizado > 0 && ($montoWizerp + 0.001) >= ($base + $cotizado);

            $solicitud->update([
                'cubierto_por_carga_masiva' => $cubierta,
                'monto_aplicado_al_cliente' => 0,
            ]);
            $marcadas++;
        }

        return $marcadas;
    }

    /**
     * @return array<string, mixed>
     */
    public function capturarSnapshotCliente(Cliente $cliente): array
    {
        $cliente->loadMissing(['listaDescuento', 'vendedor', 'tipo']);

        return [
            'monto_venta' => $cliente->monto_venta_actual,
            'lista_id' => $cliente->lista_actual_id,
            'lista_nombre' => $cliente->listaDescuento?->nombre,
            'tag_vendedor_id' => $cliente->vendedor_id,
            'tag_vendedor_nombre' => $cliente->vendedor?->name,
            'tipo_cliente_id' => $cliente->catalogo_tipo_cliente_id,
            'tipo_cliente_nombre' => $cliente->tipo?->nombre,
        ];
    }

    public function estaCubierta(SolicitudTag $solicitud): bool
    {
        return (bool) $solicitud->cubierto_por_carga_masiva;
    }

    public function capaPendiente(SolicitudTag $solicitud): float
    {
        if ($this->estaCubierta($solicitud)) {
            return 0.0;
        }

        return max(0, (float) $solicitud->monto_aplicado_al_cliente);
    }

    private function recalcularListaCliente(Cliente $cliente): void
    {
        if ($cliente->lista_bloqueada) {
            return;
        }

        if ($this->autoridad->clienteGobernadoPorModulo($cliente)) {
            $listaId = $this->proyeccionCliente->listaVigenteId($cliente);
            if ($listaId) {
                $cliente->lista_actual_id = $listaId;
            }

            return;
        }

        $listas = CatalogoListaDescuento::with('porcentajeEscalonamiento')
            ->where('activo', true)
            ->orderByDesc('monto_requerido')
            ->get();

        $listaCalificada = $this->escalonamiento->resolverListaPorAcumuladoNeto((float) $cliente->monto_venta_actual, $listas);
        $cliente->lista_actual_id = $listaCalificada ? $listaCalificada->id : null;
    }

    private function solicitudAsignaTag(SolicitudTag $solicitud): bool
    {
        $nombreProceso = strtoupper($solicitud->proceso?->nombre ?? '');

        return str_contains($nombreProceso, 'ASIGNAR TAG')
            || str_contains($nombreProceso, 'ASIGNAR CLIENTE');
    }

    private function clienteDe(SolicitudTag $solicitud): ?Cliente
    {
        if (! $solicitud->cliente_id) {
            return null;
        }

        $solicitud->loadMissing(['cliente.listaDescuento', 'cliente.vendedor', 'cliente.tipo', 'proceso', 'vendedor']);

        return $solicitud->cliente;
    }
}
