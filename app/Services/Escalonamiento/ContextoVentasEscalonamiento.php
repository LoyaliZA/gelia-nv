<?php

namespace App\Services\Escalonamiento;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\Cliente;
use App\Models\SolicitudTag;
use App\Services\Solicitudes\AcumuladoOperativoCliente;
use Illuminate\Support\Collection;

/**
 * Lo que Ventas usa para cotizar, junto al ejercicio del módulo: acumulado operativo (oficial más
 * provisionales vigentes), advertencia de posible doble conteo y la lista proyectada por una
 * solicitud pendiente. Solo lectura: no altera el acumulado del módulo, que sale de documentos,
 * ni escribe el cliente. La lista operativa es la que ya guarda el cliente por una autorización.
 */
class ContextoVentasEscalonamiento
{
    public function __construct(private AcumuladoOperativoCliente $acumulado) {}

    /**
     * @param  Collection<int, Cliente>  $clientes
     * @return array<int, array<string, mixed>>
     */
    public function paraClientes(Collection $clientes): array
    {
        if ($clientes->isEmpty()) {
            return [];
        }

        $presentados = $this->acumulado->presentarMuchos($clientes);
        $proyectadas = $this->listasProyectadas($clientes);

        $salida = [];
        foreach ($clientes as $cliente) {
            $dato = $presentados[$cliente->id];
            $salida[$cliente->id] = [
                'acumulado_oficial' => $dato['acumulado_oficial'],
                'acumulado_provisional' => $dato['acumulado_provisional'],
                'acumulado_operativo' => $dato['acumulado_operativo'],
                'acumulado_para_lista' => $dato['acumulado_para_lista'],
                'doble_conteo_posible' => $dato['doble_conteo_posible'],
                'lista_proyectada' => $proyectadas[$cliente->id] ?? null,
            ];
        }

        return $salida;
    }

    /**
     * Nombre de la lista que proyecta la solicitud pendiente más reciente de cada cliente.
     * Es solo informativa: una solicitud pendiente no concede nada.
     *
     * @param  Collection<int, Cliente>  $clientes
     * @return array<int, string|null>
     */
    public function listasProyectadas(Collection $clientes): array
    {
        $pendiente = CatalogoEstadoSolicitud::idDe('Pendiente');
        if ($clientes->isEmpty() || ! $pendiente) {
            return [];
        }

        return SolicitudTag::query()
            ->whereIn('cliente_id', $clientes->pluck('id'))
            ->where('catalogo_estado_solicitud_id', $pendiente)
            ->whereNotNull('catalogo_lista_descuento_id')
            ->with('listaDescuento:id,nombre')
            ->orderBy('id')
            ->get(['id', 'cliente_id', 'catalogo_lista_descuento_id'])
            ->groupBy('cliente_id')
            ->map(fn (Collection $filas) => $filas->last()->listaDescuento?->nombre)
            ->all();
    }

    /**
     * Operaciones comerciales que explican el acumulado temporal del cliente: pendientes (sin
     * efecto) y autorizadas con su importe provisional vigente.
     *
     * @return list<array<string, mixed>>
     */
    public function operacionesEnCurso(Cliente $cliente, int $limite = 8): array
    {
        $estados = array_filter([
            CatalogoEstadoSolicitud::idDe('Pendiente'),
            CatalogoEstadoSolicitud::idDe('Respondida'),
            CatalogoEstadoSolicitud::idDe('Verificada'),
        ]);
        if ($estados === []) {
            return [];
        }

        $solicitudes = SolicitudTag::query()
            ->where('cliente_id', $cliente->id)
            ->whereIn('catalogo_estado_solicitud_id', $estados)
            ->whereHas('proceso', fn ($q) => $q->where('categoria_flujo', '!=', \App\Models\CatalogoProceso::CATEGORIA_OPERATIVO))
            ->with(['proceso:id,nombre', 'estado:id,nombre', 'listaDescuento:id,nombre'])
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
        $provisionales = \App\Models\ImporteProvisionalSolicitud::query()
            ->whereIn('solicitud_tag_id', $solicitudes->pluck('id'))
            ->where('estado', \App\Models\ImporteProvisionalSolicitud::ESTADO_VIGENTE)
            ->pluck('importe', 'solicitud_tag_id');

        return $solicitudes->map(fn (SolicitudTag $s) => [
            'folio' => 'FOL-'.$s->id,
            'proceso' => $s->proceso?->nombre,
            'estado' => $s->estado?->nombre,
            'monto' => (string) $s->monto_cotizado,
            'pago_confirmado' => (bool) $s->pago_confirmado,
            'lista' => $s->listaDescuento?->nombre,
            'provisional_vigente' => isset($provisionales[$s->id]) ? (string) $provisionales[$s->id] : null,
        ])->all();
    }
}
