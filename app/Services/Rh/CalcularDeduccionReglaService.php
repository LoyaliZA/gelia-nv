<?php

namespace App\Services\Rh;

use App\Models\CatalogoBono;
use App\Models\CatalogoReglaIncidencia;
use App\Models\Producto;
use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use Illuminate\Validation\ValidationException;

class CalcularDeduccionReglaService
{
    public function ejecutar(
        RhColaborador $colaborador,
        CatalogoReglaIncidencia $regla,
        float $factorMultiplicador = 1.0,
        ?Producto $producto = null,
        string $origenDeduccion = RhDeduccion::ORIGEN_NOMINA,
        ?string $fechaDeduccionNomina = null,
    ): array {
        $factor = max(0.01, $factorMultiplicador);

        if ($regla->esDeduccionNomina()) {
            return $this->calcularDeduccionNomina($colaborador, $regla, $factor, $origenDeduccion, $fechaDeduccionNomina);
        }

        $base = $this->resolverMontoBase($colaborador, $regla, $producto);
        $parcial = round($base * $factor, 2);
        $totalFinal = $factor == 1.0 ? round($base, 2) : $parcial;

        return $this->empaquetarResultado(
            colaborador: $colaborador,
            regla: $regla,
            base: round($base, 2),
            factor: $factor,
            parcial: $parcial,
            totalFinal: $totalFinal,
            origenDeduccion: $origenDeduccion,
            fechaDeduccionNomina: $fechaDeduccionNomina,
        );
    }

    private function calcularDeduccionNomina(
        RhColaborador $colaborador,
        CatalogoReglaIncidencia $regla,
        float $factor,
        string $origenDeduccion,
        ?string $fechaDeduccionNomina,
    ): array {
        $deduccionSalario = $regla->aplica_deduccion_salario_base
            ? round((float) $colaborador->salario_diario * $factor, 2)
            : 0.0;

        $deduccionPuntualidad = round(
            (float) $colaborador->bono_puntualidad_diario
            * (float) $regla->factor_penalizacion_puntualidad
            * $factor,
            2,
        );

        $deduccionProductividad = round(
            (float) $colaborador->bono_productividad_diario
            * (float) $regla->factor_penalizacion_productividad
            * $factor,
            2,
        );

        $totalFinal = round($deduccionSalario + $deduccionPuntualidad + $deduccionProductividad, 2);

        return array_merge(
            $this->empaquetarResultado(
                colaborador: $colaborador,
                regla: $regla,
                base: $totalFinal,
                factor: $factor,
                parcial: $totalFinal,
                totalFinal: $totalFinal,
                origenDeduccion: $origenDeduccion,
                fechaDeduccionNomina: $fechaDeduccionNomina,
            ),
            [
                'deduccion_salario_base' => $deduccionSalario,
                'deduccion_bono_puntualidad' => $deduccionPuntualidad,
                'deduccion_bono_productividad' => $deduccionProductividad,
                'factor_puntualidad_snapshot' => $regla->factor_penalizacion_puntualidad,
                'factor_productividad_snapshot' => $regla->factor_penalizacion_productividad,
                'aplica_deduccion_salario_snapshot' => $regla->aplica_deduccion_salario_base,
            ],
        );
    }

    private function resolverMontoBase(RhColaborador $colaborador, CatalogoReglaIncidencia $regla, ?Producto $producto): float
    {
        return match ($regla->tipo_comportamiento) {
            CatalogoReglaIncidencia::COMPORTAMIENTO_COBRO_FIJO => (float) ($regla->monto_fijo ?? 0),
            CatalogoReglaIncidencia::COMPORTAMIENTO_COBRO_COSTO_PRODUCTO => $this->montoProducto($regla, $producto, 'costo'),
            CatalogoReglaIncidencia::COMPORTAMIENTO_COBRO_PRECIO_VENTA => $this->montoProducto($regla, $producto, 'precio_venta'),
            CatalogoReglaIncidencia::COMPORTAMIENTO_CANCELACION_BONO => $this->montoBonoColaborador($colaborador, $regla),
            default => throw ValidationException::withMessages([
                'catalogo_regla_incidencia_id' => 'Tipo de comportamiento no soportado para esta regla.',
            ]),
        };
    }

    private function montoProducto(CatalogoReglaIncidencia $regla, ?Producto $producto, string $campo): float
    {
        if (!$producto) {
            throw ValidationException::withMessages([
                'producto_id' => 'Debe seleccionar un producto para esta regla.',
            ]);
        }

        return (float) $producto->{$campo};
    }

    private function montoBonoColaborador(RhColaborador $colaborador, CatalogoReglaIncidencia $regla): float
    {
        if (!$regla->catalogo_bono_id) {
            return 0.0;
        }

        $bono = CatalogoBono::find($regla->catalogo_bono_id);
        if (!$bono) {
            return 0.0;
        }

        $asignado = $colaborador->bonos()->where('catalogo_bonos.id', $regla->catalogo_bono_id)->first();
        if ($asignado) {
            return (float) ($asignado->pivot->monto ?? 0);
        }

        return 0.0;
    }

    private function empaquetarResultado(
        RhColaborador $colaborador,
        CatalogoReglaIncidencia $regla,
        float $base,
        float $factor,
        float $parcial,
        float $totalFinal,
        string $origenDeduccion,
        ?string $fechaDeduccionNomina,
    ): array {
        $estado = match ($origenDeduccion) {
            RhDeduccion::ORIGEN_COMISIONES => 'pendiente_comision',
            default => !empty($fechaDeduccionNomina) ? 'pendiente_nomina' : 'pendiente_nomina',
        };

        return [
            'monto_deduccion_base' => $base,
            'factor_multiplicador' => $factor,
            'total_parcial' => $parcial,
            'monto_total_final' => $totalFinal,
            'deduccion_salario_base' => 0,
            'deduccion_bono_puntualidad' => 0,
            'deduccion_bono_productividad' => 0,
            'total_deduccion' => round($totalFinal, 2),
            'origen_deduccion' => $origenDeduccion,
            'estado_deduccion' => $estado,
            'salario_diario_snapshot' => $colaborador->salario_diario,
            'bono_puntualidad_diario_snapshot' => $colaborador->bono_puntualidad_diario,
            'bono_productividad_diario_snapshot' => $colaborador->bono_productividad_diario,
            'factor_puntualidad_snapshot' => 0,
            'factor_productividad_snapshot' => 0,
            'aplica_deduccion_salario_snapshot' => false,
            'regla_nombre_snapshot' => $regla->nombre,
            'regla_comportamiento_snapshot' => $regla->tipo_comportamiento,
            'departamento_snapshot' => $colaborador->departamento?->nombre,
            'area_snapshot' => $colaborador->area?->nombre,
        ];
    }
}
