<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\AutorizacionConsultaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\BusquedaResguardoPdvQuery;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ConsultaHistorialEntregadosResguardoPdvService
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly AutorizacionConsultaResguardoPdv $autorizacion,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     resguardos: LengthAwarePaginator,
     *     filtros: array<string, mixed>
     * }
     */
    public function payload(User $user, array $filtros = []): array
    {
        $filtrosNormalizados = $this->normalizarFiltros($filtros);

        return [
            'resguardos' => $this->listar($user, $filtrosNormalizados),
            'filtros' => $filtrosNormalizados,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(User $user, array $filtros = []): LengthAwarePaginator
    {
        $this->autorizacion->asegurarHistorialEntregados($user);

        $filtrosNormalizados = $this->normalizarFiltros($filtros);
        $query = $this->queryBase($user);
        $this->aplicarFiltros($query, $filtrosNormalizados);

        $perPage = (int) ($filtrosNormalizados['per_page'] ?? self::PER_PAGE);

        return $query
            ->paginate(max(1, min($perPage, 50)))
            ->withQueryString()
            ->through(fn (ResguardoPdv $resguardo) => $this->serializarFila($resguardo));
    }

    private function queryBase(User $user): Builder
    {
        $query = ResguardoPdv::query()
            ->where('estado', ResguardoPdv::ESTADO_ENTREGADO)
            ->with([
                'sucursal:id,nombre',
                'cliente:id,numero_cliente,nombre',
                'pedido:id,folio,folio_remision',
                'entregas' => fn ($q) => $q
                    ->with('entregadoPor:id,name,username')
                    ->orderByDesc('entregado_at')
                    ->orderByDesc('id')
                    ->limit(1),
            ]);

        return $this->alcance->aplicarConsultaPiso(
            $query,
            $user,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER_HISTORIAL_ENTREGAS
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (! empty($filtros['q'])) {
            BusquedaResguardoPdvQuery::aplicar($query, (string) $filtros['q']);
        }

        if (! empty($filtros['desde'])) {
            $desde = Carbon::parse((string) $filtros['desde'])->startOfDay();
            $query->where('entrega_completada_at', '>=', $desde);
        }

        if (! empty($filtros['hasta'])) {
            $hasta = Carbon::parse((string) $filtros['hasta'])->endOfDay();
            $query->where('entrega_completada_at', '<=', $hasta);
        }

        $query
            ->orderByDesc('entrega_completada_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarFila(ResguardoPdv $resguardo): array
    {
        $ultimaEntrega = $resguardo->entregas->first();
        $relaciones = EtiquetasResguardoPdv::relacionesEntrega();

        return [
            'id' => $resguardo->id,
            'snapshot_folio' => $resguardo->snapshot_folio,
            'snapshot_cliente_nombre' => $resguardo->snapshot_cliente_nombre,
            'referencia_cliente' => $this->referenciaCliente($resguardo),
            'entrega_completada_at' => $resguardo->entrega_completada_at?->toIso8601String(),
            'cantidad_bultos_esperada' => (int) $resguardo->cantidad_bultos_esperada,
            'pedido' => $resguardo->pedido ? [
                'id' => $resguardo->pedido->id,
                'folio' => $resguardo->pedido->folio,
                'folio_remision' => $resguardo->pedido->folio_remision,
            ] : null,
            'sucursal' => $resguardo->sucursal ? [
                'id' => $resguardo->sucursal->id,
                'nombre' => $resguardo->sucursal->nombre,
            ] : null,
            'ultima_entrega' => $this->serializarUltimaEntrega($ultimaEntrega, $relaciones),
        ];
    }

    /**
     * @param  array<string, string>  $relaciones
     * @return array<string, mixed>|null
     */
    private function serializarUltimaEntrega(?ResguardoPdvEntrega $entrega, array $relaciones): ?array
    {
        if ($entrega === null) {
            return null;
        }

        $relacion = (string) $entrega->relacion;
        $entregadoPor = $entrega->entregadoPor;

        return [
            'id' => $entrega->id,
            'relacion' => $relacion,
            'relacion_etiqueta' => $relaciones[$relacion] ?? $relacion,
            'nombre_quien_retira' => $entrega->nombre_quien_retira,
            'entregado_at' => $entrega->entregado_at?->toIso8601String(),
            'entregado_por' => $entregadoPor ? [
                'id' => $entregadoPor->id,
                'name' => $entregadoPor->name,
                'username' => $entregadoPor->username,
            ] : null,
        ];
    }

    private function referenciaCliente(ResguardoPdv $resguardo): string
    {
        $numero = $resguardo->cliente?->numero_cliente;

        if ($numero !== null && $numero !== '') {
            return '#'.(string) $numero;
        }

        return $resguardo->snapshot_folio ?: 'Sin referencia';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function normalizarFiltros(array $filtros): array
    {
        return [
            'q' => isset($filtros['q']) ? trim((string) $filtros['q']) : '',
            'desde' => ! empty($filtros['desde']) ? (string) $filtros['desde'] : null,
            'hasta' => ! empty($filtros['hasta']) ? (string) $filtros['hasta'] : null,
            'page' => max(1, (int) ($filtros['page'] ?? 1)),
            'per_page' => max(1, min((int) ($filtros['per_page'] ?? self::PER_PAGE), 50)),
        ];
    }
}
