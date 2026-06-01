<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhPrestamoPagoFijo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearPrestamoPagoFijoService
{
    public function __construct(
        private GenerarFolioPrestamoService $generarFolio,
    ) {}

    public function ejecutar(User $registrador, array $datos): RhPrestamoPagoFijo
    {
        $colaborador = RhColaborador::findOrFail($datos['rh_colaborador_id']);

        if (!$colaborador->activo) {
            throw ValidationException::withMessages([
                'rh_colaborador_id' => 'El colaborador debe estar activo.',
            ]);
        }

        $datos = $this->normalizarModalidad($datos);

        return DB::transaction(function () use ($registrador, $datos) {
            return RhPrestamoPagoFijo::create([
                'uuid' => (string) Str::uuid(),
                'folio' => $this->generarFolio->ejecutar(),
                'rh_colaborador_id' => $datos['rh_colaborador_id'],
                'concepto' => trim($datos['concepto']),
                'monto_cuota' => $datos['monto_cuota'],
                'num_pagos_total' => $datos['num_pagos_total'] ?? null,
                'pagos_realizados' => 0,
                'modalidad' => $datos['modalidad'],
                'observaciones' => $datos['observaciones'] ?? null,
                'fecha_ejecucion_programada' => $datos['fecha_ejecucion_programada'] ?? null,
                'fecha_inicio' => $datos['fecha_inicio'] ?? now()->toDateString(),
                'estado' => RhPrestamoPagoFijo::ESTADO_ACTIVO,
                'registrado_por_id' => $registrador->id,
            ])->load(['colaborador.departamento', 'colaborador.area', 'registradoPor']);
        });
    }

    private function normalizarModalidad(array $datos): array
    {
        if (($datos['modalidad'] ?? '') === RhPrestamoPagoFijo::MODALIDAD_UNICA_VEZ) {
            $datos['num_pagos_total'] = 1;
        } elseif (array_key_exists('num_pagos_total', $datos) && ($datos['num_pagos_total'] === '' || $datos['num_pagos_total'] === null)) {
            $datos['num_pagos_total'] = null;
        }

        if (($datos['modalidad'] ?? '') === RhPrestamoPagoFijo::MODALIDAD_RECURRENTE) {
            $datos['fecha_ejecucion_programada'] = null;
        }

        return $datos;
    }
}
