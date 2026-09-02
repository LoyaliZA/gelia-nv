<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ConsultaBandejasResguardoPdvService
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     bandeja: string,
     *     resguardos: LengthAwarePaginator,
     *     metricas: array<string, int>,
     *     filtros: array<string, mixed>
     * }
     */
    public function payload(User $user, array $filtros = []): array
    {
        $bandeja = $this->normalizarBandeja($filtros['bandeja'] ?? BandejaResguardoPdv::POR_RECIBIR);
        $filtrosNormalizados = $this->normalizarFiltros($filtros, $bandeja);

        return [
            'bandeja' => $bandeja,
            'resguardos' => $this->listar($user, $filtrosNormalizados),
            'metricas' => $this->metricas($user, $filtrosNormalizados),
            'filtros' => $filtrosNormalizados,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(User $user, array $filtros = []): LengthAwarePaginator
    {
        $bandeja = $this->normalizarBandeja($filtros['bandeja'] ?? BandejaResguardoPdv::POR_RECIBIR);
        $filtrosNormalizados = $this->normalizarFiltros($filtros, $bandeja);
        $query = $this->queryBandeja($user, $bandeja);
        $this->aplicarFiltrosComunes($query, $user, $filtrosNormalizados);
        $this->aplicarOrdenBandeja($query, $bandeja);

        $perPage = (int) ($filtrosNormalizados['per_page'] ?? self::PER_PAGE);

        return $query
            ->paginate(max(1, min($perPage, 50)))
            ->withQueryString()
            ->through(fn (ResguardoPdv $resguardo) => $this->serializarResguardo($resguardo));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, int>
     */
    public function metricas(User $user, array $filtros = []): array
    {
        $filtrosBase = $this->normalizarFiltros($filtros, null);
        unset($filtrosBase['bandeja'], $filtrosBase['estado'], $filtrosBase['antiguedad'], $filtrosBase['page']);

        $metricas = [];
        foreach (BandejaResguardoPdv::valores() as $bandeja) {
            $query = $this->queryBandeja($user, $bandeja);
            $this->aplicarFiltrosComunes($query, $user, $filtrosBase);
            $metricas[$bandeja] = $query->count();
        }

        return $metricas;
    }

    private function queryBandeja(User $user, string $bandeja): Builder
    {
        $query = $this->queryAutorizada($user);

        match ($bandeja) {
            BandejaResguardoPdv::POR_RECIBIR => $query->where('estado', ResguardoPdv::ESTADO_PENDIENTE_RECEPCION),
            BandejaResguardoPdv::EN_CUSTODIA => $query->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA),
            BandejaResguardoPdv::INCIDENCIAS => $query->whereHas(
                'incidencias',
                fn (Builder $incidencias) => $incidencias->where('estado', ResguardoPdvIncidencia::ESTADO_ABIERTA)
            ),
            default => null,
        };

        return $query;
    }

    private function queryAutorizada(User $user): Builder
    {
        $this->alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER);

        $query = ResguardoPdv::query()
            ->with([
                'sucursal:id,nombre',
                'cliente:id,numero_cliente,nombre',
                'pedido:id,folio,folio_remision,cliente_id',
            ])
            ->withCount([
                'incidencias as incidencias_abiertas_count' => fn (Builder $q) => $q
                    ->where('estado', ResguardoPdvIncidencia::ESTADO_ABIERTA),
            ]);

        return $this->alcance->aplicarConsultaPiso(
            $query,
            $user,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosComunes(Builder $query, User $user, array $filtros): void
    {
        $this->validarSucursalFiltro($user, $filtros['sucursal_id'] ?? null);

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        // ponytail: antiguedad se persiste en URL/UI; el cálculo depende de 3G (plazos configurables).

        if (! empty($filtros['q'])) {
            $this->aplicarBusqueda($query, (string) $filtros['q']);
        }
    }

    private function validarSucursalFiltro(User $user, mixed $sucursalId): void
    {
        if ($sucursalId === null || $sucursalId === '') {
            return;
        }

        if (! is_numeric($sucursalId)) {
            throw new AuthorizationException('Sucursal no autorizada.');
        }

        $activa = $this->alcance->sucursalActivaId($user);
        if ($activa === null || (int) $sucursalId !== $activa) {
            throw new AuthorizationException('Sucursal no autorizada.');
        }
    }

    private function aplicarBusqueda(Builder $query, string $termino): void
    {
        $termino = trim($termino);
        if ($termino === '') {
            return;
        }

        $like = '%'.$termino.'%';

        $query->where(function (Builder $q) use ($like, $termino) {
            $q->where('snapshot_folio', 'like', $like)
                ->orWhere('snapshot_cliente_nombre', 'like', $like)
                ->orWhereHas('pedido', function (Builder $pedido) use ($like, $termino) {
                    $pedido->where('folio', 'like', $like)
                        ->orWhere('folio_remision', 'like', $like);

                    if (is_numeric($termino)) {
                        $pedido->orWhere('id', (int) $termino);
                    }
                })
                ->orWhereHas('cliente', function (Builder $cliente) use ($like, $termino) {
                    $cliente->where('nombre', 'like', $like);

                    if (is_numeric($termino)) {
                        $cliente->orWhere('numero_cliente', 'like', $like);
                    }
                });
        });
    }

    private function aplicarOrdenBandeja(Builder $query, string $bandeja): void
    {
        match ($bandeja) {
            BandejaResguardoPdv::POR_RECIBIR => $query
                ->orderBy('salida_cedis_at')
                ->orderBy('id'),
            BandejaResguardoPdv::EN_CUSTODIA => $query
                ->orderBy('recepcion_fisica_at')
                ->orderBy('id'),
            BandejaResguardoPdv::INCIDENCIAS => $query
                ->withMax(
                    ['incidencias as ultima_incidencia_abierta_at' => fn (Builder $q) => $q
                        ->where('estado', ResguardoPdvIncidencia::ESTADO_ABIERTA)],
                    'reportado_at'
                )
                ->orderByDesc('ultima_incidencia_abierta_at')
                ->orderBy('id'),
            default => $query->orderByDesc('updated_at')->orderBy('id'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarResguardo(ResguardoPdv $resguardo): array
    {
        return [
            'id' => $resguardo->id,
            'estado' => $resguardo->estado,
            'pedido_bma_id' => $resguardo->pedido_bma_id,
            'snapshot_folio' => $resguardo->snapshot_folio,
            'snapshot_cliente_nombre' => $resguardo->snapshot_cliente_nombre,
            'cantidad_bultos_esperada' => $resguardo->cantidad_bultos_esperada,
            'salida_cedis_at' => $resguardo->salida_cedis_at?->toIso8601String(),
            'recepcion_fisica_at' => $resguardo->recepcion_fisica_at?->toIso8601String(),
            'entrega_bloqueada' => $resguardo->entrega_bloqueada,
            'incidencias_abiertas_count' => (int) ($resguardo->incidencias_abiertas_count ?? 0),
            'sucursal' => $resguardo->sucursal ? [
                'id' => $resguardo->sucursal->id,
                'nombre' => $resguardo->sucursal->nombre,
            ] : null,
            'cliente' => $resguardo->cliente ? [
                'id' => $resguardo->cliente->id,
                'numero_cliente' => $resguardo->cliente->numero_cliente,
                'nombre' => $resguardo->cliente->nombre,
            ] : null,
            'pedido' => $resguardo->pedido ? [
                'id' => $resguardo->pedido->id,
                'folio' => $resguardo->pedido->folio,
                'folio_remision' => $resguardo->pedido->folio_remision,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function normalizarFiltros(array $filtros, ?string $bandejaForzada): array
    {
        $bandeja = $bandejaForzada ?? $this->normalizarBandeja($filtros['bandeja'] ?? BandejaResguardoPdv::POR_RECIBIR);

        return [
            'bandeja' => $bandeja,
            'q' => isset($filtros['q']) ? trim((string) $filtros['q']) : null,
            'estado' => $filtros['estado'] ?? null,
            'antiguedad' => $filtros['antiguedad'] ?? null,
            'sucursal_id' => isset($filtros['sucursal_id']) ? (int) $filtros['sucursal_id'] : null,
            'page' => isset($filtros['page']) ? (int) $filtros['page'] : null,
            'per_page' => isset($filtros['per_page']) ? (int) $filtros['per_page'] : self::PER_PAGE,
        ];
    }

    private function normalizarBandeja(string $bandeja): string
    {
        return in_array($bandeja, BandejaResguardoPdv::valores(), true)
            ? $bandeja
            : BandejaResguardoPdv::POR_RECIBIR;
    }
}
