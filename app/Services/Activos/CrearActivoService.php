<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\CatalogoTipoActivo;
use App\Models\Departamento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrearActivoService
{
    public function __construct(
        private ValidarAtributosActivoService $validarAtributos,
        private RegistrarMovimientoActivoService $registrarMovimiento,
        private SincronizarMarcaModeloActivoService $sincronizarMarcaModelo,
        private GenerarFolioActivoService $generarFolio,
        private ConstruirSnapshotActivoService $construirSnapshot,
    ) {}

    public function ejecutar(User $usuario, array $datos): Activo
    {
        $tipo = CatalogoTipoActivo::findOrFail($datos['catalogo_tipo_activo_id']);
        $departamento = Departamento::findOrFail($datos['departamento_id']);
        $atributos = $this->validarAtributos->ejecutar($tipo, $datos['atributos'] ?? []);
        $this->sincronizarMarcaModelo->ejecutar($tipo, $atributos);
        $fechaVencimiento = $datos['fecha_vencimiento']
            ?? $this->validarAtributos->sincronizarFechaVencimiento($tipo, $atributos);

        return DB::transaction(function () use ($usuario, $datos, $tipo, $departamento, $atributos, $fechaVencimiento) {
            $activo = Activo::create([
                'folio' => $this->generarFolio->ejecutar($tipo, $departamento),
                'consulta_token' => (string) Str::uuid(),
                'catalogo_tipo_activo_id' => $tipo->id,
                'catalogo_categoria_activo_id' => $datos['catalogo_categoria_activo_id'] ?? null,
                'activo_padre_id' => $datos['activo_padre_id'] ?? null,
                'departamento_id' => $datos['departamento_id'],
                'area_id' => $datos['area_id'] ?? null,
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? null,
                'estado' => 'disponible',
                'atributos' => $atributos,
                'fecha_adquisicion' => $datos['fecha_adquisicion'] ?? null,
                'fecha_vencimiento' => $fechaVencimiento,
                'valor' => $datos['valor'] ?? null,
                'registrado_por_id' => $usuario->id,
            ]);

            $activo->load(['tipo', 'departamento']);

            $this->registrarMovimiento->ejecutar($activo, $usuario, 'creacion', [
                'datos_snapshot' => $this->construirSnapshot->ejecutar($activo),
            ]);

            return $activo->load(['tipo', 'departamento', 'area', 'responsable', 'categoria', 'padre']);
        });
    }
}
