<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Http\Request;

class ResolverPeriodoDashboard
{
    public const HOY = 'hoy';

    public const SIETE_DIAS = '7d';

    public const MES = 'mes';

    public const RANGO = 'rango';

    public const GRANOS_DIA = 'dia';

    public const GRANOS_SEMANA = 'semana';

    /**
     * @return array{
     *     clave: string,
     *     desde: string,
     *     hasta: string,
     *     inicio: Carbon,
     *     fin: Carbon,
     *     comparacion_inicio: Carbon,
     *     comparacion_fin: Carbon,
     *     grano: string,
     *     etiqueta: string,
     *     etiqueta_anterior: string,
     *     dias: int
     * }
     */
    public function resolver(Request $request, ?Carbon $ahora = null): array
    {
        $ahora = ($ahora ?? now())->copy();
        $clave = $request->input('periodo', self::MES);
        if (! in_array($clave, [self::HOY, self::SIETE_DIAS, self::MES, self::RANGO], true)) {
            $clave = self::MES;
        }

        if ($clave === self::HOY) {
            $inicio = $ahora->copy()->startOfDay();
            $fin = $ahora->copy()->endOfDay();
        } elseif ($clave === self::SIETE_DIAS) {
            $fin = $ahora->copy()->endOfDay();
            $inicio = $ahora->copy()->subDays(6)->startOfDay();
        } elseif ($clave === self::RANGO) {
            $desde = $this->parseFecha($request->input('desde'));
            $hasta = $this->parseFecha($request->input('hasta'));
            if ($desde === null || $hasta === null || $desde->gt($hasta)) {
                $clave = self::MES;
                $inicio = $ahora->copy()->startOfMonth();
                $fin = $ahora->copy()->endOfDay();
            } else {
                $inicio = $desde->copy()->startOfDay();
                $fin = $hasta->copy()->endOfDay();
                if ($inicio->diffInDays($fin) > 366) {
                    $fin = $inicio->copy()->addDays(366)->endOfDay();
                }
            }
        } else {
            $clave = self::MES;
            $inicio = $ahora->copy()->startOfMonth();
            $fin = $ahora->copy()->endOfDay();
        }

        $dias = (int) $inicio->copy()->startOfDay()->diffInDays($fin->copy()->startOfDay()) + 1;
        $comparacionFin = $inicio->copy()->subDay()->endOfDay();
        $comparacionInicio = $inicio->copy()->subDays($dias)->startOfDay();
        $grano = $dias > 31 ? self::GRANOS_SEMANA : self::GRANOS_DIA;

        return [
            'clave' => $clave,
            'desde' => $inicio->toDateString(),
            'hasta' => $fin->toDateString(),
            'inicio' => $inicio,
            'fin' => $fin,
            'comparacion_inicio' => $comparacionInicio,
            'comparacion_fin' => $comparacionFin,
            'grano' => $grano,
            'etiqueta' => $this->etiqueta($clave, $inicio, $fin),
            'etiqueta_anterior' => $this->etiquetaRango($comparacionInicio, $comparacionFin),
            'dias' => $dias,
        ];
    }

    /**
     * @param  array<string, mixed>  $periodo
     * @return array<string, mixed>
     */
    public function paraInertia(array $periodo): array
    {
        return [
            'clave' => $periodo['clave'],
            'desde' => $periodo['desde'],
            'hasta' => $periodo['hasta'],
            'grano' => $periodo['grano'],
            'etiqueta' => $periodo['etiqueta'],
            'etiqueta_anterior' => $periodo['etiqueta_anterior'],
        ];
    }

    /**
     * @param  array<string, mixed>  $periodo
     * @return array<string, mixed>
     */
    public function parametrosContabilidad(array $periodo): array
    {
        if ($periodo['clave'] === self::HOY) {
            return ['filtro' => 'dia', 'fecha' => $periodo['desde']];
        }

        if ($periodo['clave'] === self::MES) {
            $inicio = Carbon::parse($periodo['desde']);

            return [
                'filtro' => 'mes',
                'mes' => $inicio->month,
                'anio' => $inicio->year,
            ];
        }

        return [
            'filtro' => 'custom',
            'inicio' => $periodo['desde'],
            'fin' => $periodo['hasta'],
        ];
    }

    private function parseFecha(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    private function etiqueta(string $clave, Carbon $inicio, Carbon $fin): string
    {
        if ($clave === self::HOY) {
            return 'Hoy';
        }

        if ($clave === self::SIETE_DIAS) {
            return 'Últimos 7 días';
        }

        if ($clave === self::MES) {
            return $inicio->copy()->locale('es')->translatedFormat('F Y');
        }

        return $this->etiquetaRango($inicio, $fin);
    }

    private function etiquetaRango(Carbon $inicio, Carbon $fin): string
    {
        $inicio->locale('es');
        $fin->locale('es');

        if ($inicio->isSameDay($fin)) {
            return $inicio->translatedFormat('j M Y');
        }

        if ($inicio->isSameMonth($fin) && $inicio->isSameYear($fin)) {
            return $inicio->translatedFormat('j').'–'.$fin->translatedFormat('j M Y');
        }

        return $inicio->translatedFormat('j M').' – '.$fin->translatedFormat('j M Y');
    }
}
