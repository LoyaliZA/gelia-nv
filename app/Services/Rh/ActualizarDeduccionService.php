<?php

namespace App\Services\Rh;

use App\Models\CatalogoReglaIncidencia;
use App\Models\Producto;
use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActualizarDeduccionService
{
    public function __construct(
        private CalcularDeduccionReglaService $calcularDeduccion,
        private GuardarFirmaDeduccionService $guardarFirma,
    ) {}

    public function ejecutar(User $editor, RhDeduccion $registro, array $datos): RhDeduccion
    {
        if ($registro->estado_deduccion === RhDeduccion::ESTADO_APLICADO) {
            throw ValidationException::withMessages([
                'estado_deduccion' => 'No se pueden editar deducciones ya aplicadas.',
            ]);
        }

        $colaborador = RhColaborador::with(['departamento', 'area', 'bonos'])->findOrFail($datos['rh_colaborador_id']);
        $regla = CatalogoReglaIncidencia::findOrFail($datos['catalogo_regla_incidencia_id']);

        if (!$colaborador->activo || !$regla->activo) {
            throw ValidationException::withMessages([
                'rh_colaborador_id' => 'Colaborador o concepto no válido.',
            ]);
        }

        if (!$regla->disponiblePara($colaborador, $editor)) {
            throw ValidationException::withMessages([
                'catalogo_regla_incidencia_id' => 'El concepto no está disponible.',
            ]);
        }

        $producto = $regla->requiereProducto() ? Producto::findOrFail($datos['producto_id'] ?? 0) : null;
        $factor = (float) ($datos['factor_multiplicador'] ?? 1);
        $origen = $datos['origen_deduccion'] ?? RhDeduccion::ORIGEN_NOMINA;
        $fechaDeduccion = $datos['fecha_deduccion_nomina'] ?? null;

        $calculado = $this->calcularDeduccion->ejecutar($colaborador, $regla, $factor, $producto, $origen, $fechaDeduccion);

        return DB::transaction(function () use ($registro, $datos, $calculado, $regla, $producto, $fechaDeduccion, $colaborador) {
            $registro->update(array_merge([
                'fecha_ocurrencia' => $datos['fecha_ocurrencia'],
                'rh_colaborador_id' => $colaborador->id,
                'catalogo_regla_incidencia_id' => $regla->id,
                'producto_id' => $producto?->id,
                'producto_sku_snapshot' => $producto?->sku,
                'descripcion_detallada' => $datos['descripcion_detallada'] ?? null,
                'fecha_deduccion_nomina' => $fechaDeduccion,
            ], $calculado));

            if (!empty($datos['firma_gerente_data'])) {
                $registro->update([
                    'firma_gerente_path' => $this->guardarFirma->ejecutar($registro, 'gerente', $datos['firma_gerente_data']),
                ]);
            }

            if (!empty($datos['firma_colaborador_data'])) {
                $registro->update([
                    'firma_colaborador_path' => $this->guardarFirma->ejecutar($registro, 'colaborador', $datos['firma_colaborador_data']),
                ]);
            }

            return $registro->fresh([
                'colaborador.departamento',
                'colaborador.area',
                'colaborador.puesto',
                'reglaIncidencia',
                'registradoPor',
                'comisionAuditor',
                'producto',
            ]);
        });
    }
}
