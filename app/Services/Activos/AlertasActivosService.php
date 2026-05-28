<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\ActivoMantenimiento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AlertasActivosService
{
    public function ejecutar(?User $usuario = null, int $diasDefault = 30): array
    {
        $query = Activo::with(['tipo', 'departamento', 'responsable'])
            ->whereNotIn('estado', ['baja']);

        if ($usuario && !$usuario->hasRole(['Super Admin', 'Administrador']) && !$usuario->can('activos.ver_todos')) {
            $departamentos = $usuario->departamentos->pluck('id')->toArray();
            if (empty($departamentos)) {
                return ['vencidos' => [], 'proximos_7' => [], 'proximos_30' => [], 'mantenimiento' => []];
            }
            $query->whereIn('departamento_id', $departamentos);
        }

        $activos = $query->get();
        $hoy = Carbon::today();

        return [
            'vencidos' => $this->filtrarVencimientos($activos, $hoy, 'vencido'),
            'proximos_7' => $this->filtrarVencimientos($activos, $hoy, '7'),
            'proximos_30' => $this->filtrarVencimientos($activos, $hoy, '30'),
            'mantenimiento' => array_merge(
                $this->filtrarMantenimientoAtributos($activos, $hoy),
                $this->filtrarMantenimientosProgramados($usuario, $hoy)
            ),
        ];
    }

    private function filtrarVencimientos(Collection $activos, Carbon $hoy, string $tipo): array
    {
        return $activos->filter(function (Activo $activo) use ($hoy, $tipo) {
            $fecha = $this->obtenerFechaVencimiento($activo);
            if (!$fecha) {
                return false;
            }

            return match ($tipo) {
                'vencido' => $fecha->lt($hoy),
                '7' => $fecha->gte($hoy) && $fecha->lte($hoy->copy()->addDays(7)),
                '30' => $fecha->gt($hoy->copy()->addDays(7)) && $fecha->lte($hoy->copy()->addDays(30)),
                default => false,
            };
        })->values()->map(fn (Activo $a) => $this->formatearAlerta($a, $this->obtenerFechaVencimiento($a)))->all();
    }

    private function filtrarMantenimientoAtributos(Collection $activos, Carbon $hoy): array
    {
        return $activos->filter(function (Activo $activo) use ($hoy) {
            $fecha = $this->obtenerFechaAtributo($activo, 'proximo_mantenimiento');
            return $fecha && $fecha->lte($hoy->copy()->addDays(14));
        })->values()->map(function (Activo $a) {
            return $this->formatearAlerta($a, $this->obtenerFechaAtributo($a, 'proximo_mantenimiento'), 'mantenimiento');
        })->all();
    }

    private function filtrarMantenimientosProgramados(?User $usuario, Carbon $hoy): array
    {
        $query = ActivoMantenimiento::with(['activo.tipo', 'activo.departamento', 'activo.responsable'])
            ->whereIn('estado', ['programado', 'en_proceso'])
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_programada')
                    ->orWhere('fecha_programada', '<=', $hoy->copy()->addDays(14));
            });

        if ($usuario && !$usuario->hasRole(['Super Admin', 'Administrador']) && !$usuario->can('activos.ver_todos')) {
            $departamentos = $usuario->departamentos->pluck('id')->toArray();
            if (empty($departamentos)) {
                return [];
            }
            $query->whereHas('activo', fn ($q) => $q->whereIn('departamento_id', $departamentos));
        }

        return $query->get()->map(function (ActivoMantenimiento $m) {
            return $this->formatearAlerta(
                $m->activo,
                $m->fecha_programada ? Carbon::parse($m->fecha_programada) : null,
                'mantenimiento_programado'
            );
        })->all();
    }

    private function obtenerFechaVencimiento(Activo $activo): ?Carbon
    {
        if ($activo->fecha_vencimiento) {
            return Carbon::parse($activo->fecha_vencimiento);
        }

        return $this->obtenerFechaAtributo($activo, 'fecha_vencimiento');
    }

    private function obtenerFechaAtributo(Activo $activo, string $key): ?Carbon
    {
        $valor = $activo->atributos[$key] ?? null;
        return $valor ? Carbon::parse($valor) : null;
    }

    private function formatearAlerta(Activo $activo, ?Carbon $fecha, string $tipo = 'vencimiento'): array
    {
        return [
            'id' => $activo->id,
            'folio' => $activo->folio,
            'nombre' => $activo->nombre,
            'tipo' => $activo->tipo?->nombre,
            'departamento' => $activo->departamento?->nombre,
            'responsable' => $activo->responsable?->name,
            'fecha' => $fecha?->toDateString(),
            'alerta_tipo' => $tipo,
        ];
    }
}
