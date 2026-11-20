<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\CatalogoTipoActivo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ActualizarActivoService
{
    public function __construct(
        private ValidarAtributosActivoService $validarAtributos,
        private RegistrarMovimientoActivoService $registrarMovimiento,
        private SincronizarMarcaModeloActivoService $sincronizarMarcaModelo,
        private ConstruirSnapshotActivoService $construirSnapshot,
    ) {}

    public function ejecutar(Activo $activo, User $usuario, array $datos): Activo
    {
        $tipo = $activo->tipo;
        if (!empty($datos['catalogo_tipo_activo_id'])) {
            $tipo = CatalogoTipoActivo::findOrFail($datos['catalogo_tipo_activo_id']);
        }
        $atributos = $this->validarAtributos->ejecutar($tipo, $datos['atributos'] ?? ($activo->atributos ?? []));
        $this->sincronizarMarcaModelo->ejecutar($tipo, $atributos);
        $fechaVencimiento = $datos['fecha_vencimiento']
            ?? $this->validarAtributos->sincronizarFechaVencimiento($tipo, $atributos)
            ?? $activo->fecha_vencimiento;

        return DB::transaction(function () use ($activo, $usuario, $datos, $atributos, $fechaVencimiento) {
            $activo->load(['tipo', 'departamento']);
            $snapshot = $this->construirSnapshot->ejecutar($activo);

            $activo->update([
                'catalogo_tipo_activo_id' => $datos['catalogo_tipo_activo_id'] ?? $activo->catalogo_tipo_activo_id,
                'catalogo_categoria_activo_id' => array_key_exists('catalogo_categoria_activo_id', $datos)
                    ? $datos['catalogo_categoria_activo_id']
                    : $activo->catalogo_categoria_activo_id,
                'activo_padre_id' => array_key_exists('activo_padre_id', $datos)
                    ? $datos['activo_padre_id']
                    : $activo->activo_padre_id,
                'area_id' => array_key_exists('area_id', $datos) ? $datos['area_id'] : $activo->area_id,
                'nombre' => $datos['nombre'] ?? $activo->nombre,
                'descripcion' => array_key_exists('descripcion', $datos) ? $datos['descripcion'] : $activo->descripcion,
                'atributos' => $atributos,
                'fecha_adquisicion' => array_key_exists('fecha_adquisicion', $datos)
                    ? $datos['fecha_adquisicion']
                    : $activo->fecha_adquisicion,
                'fecha_vencimiento' => $fechaVencimiento,
                'valor' => array_key_exists('valor', $datos) ? $datos['valor'] : $activo->valor,
            ]);

            $this->registrarMovimiento->ejecutar($activo, $usuario, 'edicion', [
                'datos_snapshot' => $snapshot,
            ]);

            return $activo->fresh(['tipo', 'departamento', 'area', 'responsable', 'categoria', 'padre']);
        });
    }
}
