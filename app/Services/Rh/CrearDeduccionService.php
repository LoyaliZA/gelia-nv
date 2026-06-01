<?php

namespace App\Services\Rh;

use App\Models\Producto;
use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhMovimientoComisionColaborador;
use App\Models\CatalogoReglaIncidencia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearDeduccionService
{
    public function __construct(
        private CalcularDeduccionReglaService $calcularDeduccion,
        private GenerarFolioDeduccionService $generarFolio,
        private RegistrarComisionAuditorService $registrarComisionAuditor,
        private GuardarFirmaDeduccionService $guardarFirma,
    ) {}

    public function ejecutar(User $registrador, array $datos): RhDeduccion
    {
        $colaborador = RhColaborador::with(['departamento', 'area', 'bonos'])->findOrFail($datos['rh_colaborador_id']);
        $regla = CatalogoReglaIncidencia::findOrFail($datos['catalogo_regla_incidencia_id']);

        if (!$colaborador->activo) {
            throw ValidationException::withMessages([
                'rh_colaborador_id' => 'El colaborador debe estar activo.',
            ]);
        }

        if (!$regla->activo) {
            throw ValidationException::withMessages([
                'catalogo_regla_incidencia_id' => 'El concepto seleccionado debe estar activo.',
            ]);
        }

        if (!$regla->disponiblePara($colaborador, $registrador)) {
            throw ValidationException::withMessages([
                'catalogo_regla_incidencia_id' => 'El concepto no está disponible para este colaborador o usuario.',
            ]);
        }

        $producto = null;
        if ($regla->requiereProducto()) {
            $producto = Producto::findOrFail($datos['producto_id'] ?? 0);
        }

        $factor = (float) ($datos['factor_multiplicador'] ?? 1);
        $origen = $datos['origen_deduccion'] ?? RhDeduccion::ORIGEN_NOMINA;
        $fechaDeduccion = $datos['fecha_deduccion_nomina'] ?? null;

        $calculado = $this->calcularDeduccion->ejecutar(
            $colaborador,
            $regla,
            $factor,
            $producto,
            $origen,
            $fechaDeduccion,
        );

        return DB::transaction(function () use ($registrador, $datos, $calculado, $regla, $producto, $fechaDeduccion, $colaborador, $origen) {
            $registro = RhDeduccion::create(array_merge([
                'uuid' => (string) Str::uuid(),
                'folio' => $this->generarFolio->ejecutar(),
                'fecha_ocurrencia' => $datos['fecha_ocurrencia'],
                'rh_colaborador_id' => $colaborador->id,
                'catalogo_regla_incidencia_id' => $regla->id,
                'producto_id' => $producto?->id,
                'producto_sku_snapshot' => $producto?->sku,
                'descripcion_detallada' => $datos['descripcion_detallada'] ?? null,
                'fecha_deduccion_nomina' => $fechaDeduccion,
                'registrado_por_id' => $registrador->id,
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

            $this->registrarComisionAuditor->ejecutar($registrador, $registro, $regla);

            if ($origen === RhDeduccion::ORIGEN_COMISIONES) {
                $this->registrarCargoComisionColaborador($colaborador, $registro, $calculado['monto_total_final']);
            }

            return $registro->load([
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

    private function registrarCargoComisionColaborador(RhColaborador $colaborador, RhDeduccion $deduccion, float $monto): void
    {
        $colaborador->refresh();
        $nuevoSaldo = max(0, (float) $colaborador->saldo_comisiones - $monto);
        $colaborador->update(['saldo_comisiones' => $nuevoSaldo]);

        RhMovimientoComisionColaborador::create([
            'rh_colaborador_id' => $colaborador->id,
            'rh_deduccion_id' => $deduccion->id,
            'tipo' => RhMovimientoComisionColaborador::TIPO_CARGO,
            'monto' => $monto,
            'saldo_resultante' => $nuevoSaldo,
            'concepto' => "Deducción {$deduccion->folio}",
        ]);
    }
}
