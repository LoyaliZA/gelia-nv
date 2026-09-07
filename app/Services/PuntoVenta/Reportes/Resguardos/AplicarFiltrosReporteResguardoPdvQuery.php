<?php

namespace App\Services\PuntoVenta\Reportes\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\CalcularAntiguedadOperativaResguardoPdvService;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class AplicarFiltrosReporteResguardoPdvQuery
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly CalcularAntiguedadOperativaResguardoPdvService $antiguedad,
    ) {}

    public function asegurarAcceso(User $user): void
    {
        $this->alcance->asegurarConsultaGlobal($user);

        if (! $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER)) {
            throw new AuthorizationException('No autorizado para consultar métricas de resguardos.');
        }
    }

    public function queryResguardos(User $user): Builder
    {
        $this->asegurarAcceso($user);

        return $this->alcance->aplicarConsultaGlobal(ResguardoPdv::query(), $user);
    }

    public function queryEventos(User $user): Builder
    {
        $this->asegurarAcceso($user);

        $sucursales = $this->alcance->idsSucursalesElegibles();

        return ResguardoPdvEvento::query()
            ->whereHas('resguardo', fn (Builder $q) => $q->whereIn('sucursal_id', $sucursales));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function aplicarFiltrosResguardo(Builder $query, User $user, array $filtros): Builder
    {
        $this->validarSucursal($user, $filtros['sucursal_id'] ?? null);

        if (! empty($filtros['sucursal_id'])) {
            $query->where('sucursal_id', (int) $filtros['sucursal_id']);
        }

        if (! empty($filtros['estado'])) {
            $query->where('estado', (string) $filtros['estado']);
        }

        if (! empty($filtros['antiguedad'])) {
            $this->restringirPorAntiguedad(
                $query,
                (string) $filtros['antiguedad'],
                $filtros['corte_reporte_at']
            );
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function aplicarFiltrosEvento(Builder $query, array $filtros): Builder
    {
        if (! empty($filtros['sucursal_id'])) {
            $query->whereHas(
                'resguardo',
                fn (Builder $q) => $q->where('sucursal_id', (int) $filtros['sucursal_id'])
            );
        }

        if (! empty($filtros['tipo_incidencia'])) {
            $query->whereHas(
                'resguardo.incidencias',
                fn (Builder $q) => $q->where('tipo', (string) $filtros['tipo_incidencia'])
            );
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    public function idsSucursalesDesglose(User $user, array $filtros): array
    {
        if (! empty($filtros['sucursal_id'])) {
            return [(int) $filtros['sucursal_id']];
        }

        return $this->alcance->idsSucursalesElegibles()->all();
    }

    private function validarSucursal(User $user, mixed $sucursalId): void
    {
        if ($sucursalId === null || $sucursalId === '') {
            return;
        }

        if (! is_numeric($sucursalId)) {
            throw new AuthorizationException('Sucursal no autorizada.');
        }

        if (! $this->alcance->idsSucursalesElegibles()->contains((int) $sucursalId)) {
            throw new AuthorizationException('Sucursal no autorizada.');
        }
    }

    private function restringirPorAntiguedad(Builder $query, string $antiguedad, Carbon $corte): void
    {
        $ids = (clone $query)->pluck('id');
        if ($ids->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $permitidos = ResguardoPdv::query()
            ->whereIn('id', $ids)
            ->get([
                'id',
                'sucursal_id',
                'estado',
                'salida_cedis_at',
                'recepcion_fisica_at',
                'entrega_completada_at',
                'devolucion_confirmada_at',
                'vencido_repuesto_at',
            ])
            ->filter(fn (ResguardoPdv $resguardo) => $this->antiguedad->coincideConFiltro($resguardo, $antiguedad, $corte))
            ->pluck('id');

        if ($permitidos->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('id', $permitidos->all());
    }

    /**
     * @return array{rezagado: int, proximo_a_vencer: int, vencido: int}
     */
    public function contarClasificaciones(Builder $query, Carbon $corte): array
    {
        $conteos = [
            AntiguedadOperativaResguardoPdv::REZAGADO => 0,
            AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER => 0,
            AntiguedadOperativaResguardoPdv::VENCIDO => 0,
        ];

        (clone $query)
            ->select([
                'id',
                'sucursal_id',
                'estado',
                'salida_cedis_at',
                'recepcion_fisica_at',
                'entrega_completada_at',
                'devolucion_confirmada_at',
                'vencido_repuesto_at',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($resguardos) use ($corte, &$conteos) {
                foreach ($resguardos as $resguardo) {
                    $evaluacion = $this->antiguedad->evaluar($resguardo, $corte);
                    foreach ($conteos as $clasificacion => $_) {
                        if ($evaluacion['clasificaciones'][$clasificacion] ?? false) {
                            $conteos[$clasificacion]++;
                        }
                    }
                }
            });

        return [
            'rezagado' => $conteos[AntiguedadOperativaResguardoPdv::REZAGADO],
            'proximo_a_vencer' => $conteos[AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER],
            'vencido' => $conteos[AntiguedadOperativaResguardoPdv::VENCIDO],
        ];
    }
}
