<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\CatalogoEstatusPago;
use App\Models\Contabilidad\Pedido;
use App\Models\Contabilidad\PlataformaPago;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ObtenerRetirosContabilidadService
{
    /**
     * @return array{datos_plataformas: array<int, array<string, mixed>>}
     */
    public function ejecutar(): array
    {
        $plataformas = PlataformaPago::query()
            ->with('frecuenciaPago')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $pedidosPendientes = Pedido::query()
            ->with(['tipoTransaccion', 'plataformaPago.frecuenciaPago'])
            ->where('estatus_pago_id', CatalogoEstatusPago::PENDIENTE)
            ->orderBy('fecha_salida')
            ->get();

        $datosPlataformas = [];

        foreach ($plataformas as $plataforma) {
            $pedidos = $pedidosPendientes
                ->where('plataforma_pago_id', $plataforma->id)
                ->values();

            $frecuenciaCodigo = strtolower($plataforma->frecuenciaPago?->codigo ?? 'mensual');

            $grupos = $pedidos
                ->groupBy(fn (Pedido $pedido) => $this->nombreGrupo($pedido, $frecuenciaCodigo))
                ->sortBy(fn (Collection $grupo) => $grupo->min('fecha_salida'))
                ->map(fn (Collection $grupo) => $grupo->values()->all())
                ->all();

            $datosPlataformas[] = [
                'plataforma' => $plataforma->only([
                    'id', 'nombre', 'tasa_comision_pct', 'cuota_fija', 'tasa_iva_pct',
                ]),
                'grupos' => $grupos,
                'total_pendientes' => $pedidos->count(),
            ];
        }

        return ['datos_plataformas' => $datosPlataformas];
    }

    private function nombreGrupo(Pedido $pedido, string $frecuenciaCodigo): string
    {
        $fecha = Carbon::parse($pedido->fecha_salida);

        return match ($frecuenciaCodigo) {
            'semanal' => 'Semana del '.$fecha->copy()->startOfWeek()->format('d/m/Y')
                .' al '.$fecha->copy()->endOfWeek()->format('d/m/Y'),
            'quincenal' => $fecha->day <= 15
                ? 'Quincena 01/'.$fecha->format('m/Y').' al 15/'.$fecha->format('m/Y')
                : 'Quincena 16/'.$fecha->format('m/Y').' al '.$fecha->copy()->endOfMonth()->format('d/m/Y'),
            'diario', 'inmediato' => 'Día '.$fecha->format('d/m/Y'),
            default => 'Periodo '.$fecha->format('F Y'),
        };
    }
}
