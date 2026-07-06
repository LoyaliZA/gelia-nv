<?php

namespace App\Services\Rh;

use App\Models\Area;
use App\Models\RhColaborador;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearColaboradorService
{
    public function __construct(
        private GenerarFolioColaboradorService $generarFolio,
        private CalcularSalariosColaboradorService $calcularSalarios,
        private ValidarBonosColaboradorService $validarBonos,
        private SincronizarBonosColaboradorService $sincronizarBonos,
    ) {}

    public function ejecutar(User $registrador, array $datos): RhColaborador
    {
        $this->validarArea($datos);
        $this->validarBonos->ejecutar((int) $datos['catalogo_puesto_id'], $datos['bonos'] ?? []);

        return DB::transaction(function () use ($registrador, $datos) {
            $colaborador = new RhColaborador([
                'uuid' => (string) Str::uuid(),
                'folio' => $this->generarFolio->ejecutar(),
                'user_id' => $datos['user_id'] ?? null,
                'departamento_id' => $datos['departamento_id'],
                'area_id' => $datos['area_id'] ?? null,
                'nombre' => $datos['nombre'],
                'apellido_paterno' => $datos['apellido_paterno'] ?? null,
                'apellido_materno' => $datos['apellido_materno'] ?? null,
                'catalogo_puesto_id' => $datos['catalogo_puesto_id'],
                'catalogo_turno_id' => $datos['catalogo_turno_id'],
                'salario_base' => $datos['salario_base'] ?? 0,
                'bono_productividad' => $datos['bono_productividad'] ?? 0,
                'bono_puntualidad' => $datos['bono_puntualidad'] ?? 0,
                'horas_laboradas_oficiales' => $datos['horas_laboradas_oficiales'] ?? 8,
                'activo' => $datos['activo'] ?? true,
                'registrado_por_id' => $registrador->id,
            ]);

            $this->calcularSalarios->ejecutar($colaborador);
            $colaborador->save();

            $this->sincronizarBonos->ejecutar($colaborador, $datos['bonos'] ?? []);

            return $colaborador->load(['departamento', 'area', 'puesto', 'usuario', 'registradoPor', 'bonos']);
        });
    }

    private function validarArea(array $datos): void
    {
        if (empty($datos['area_id'])) {
            return;
        }

        $area = Area::find($datos['area_id']);
        if (!$area || (int) $area->departamento_id !== (int) $datos['departamento_id']) {
            throw ValidationException::withMessages([
                'area_id' => 'El área seleccionada no pertenece al departamento indicado.',
            ]);
        }
    }
}
