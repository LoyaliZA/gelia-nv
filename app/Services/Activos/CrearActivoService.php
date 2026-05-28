<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\CatalogoTipoActivo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CrearActivoService
{
    public function __construct(
        private ValidarAtributosActivoService $validarAtributos,
        private RegistrarMovimientoActivoService $registrarMovimiento,
        private SincronizarMarcaModeloActivoService $sincronizarMarcaModelo,
    ) {}

    public function ejecutar(User $usuario, array $datos): Activo
    {
        $tipo = CatalogoTipoActivo::findOrFail($datos['catalogo_tipo_activo_id']);
        $atributos = $this->validarAtributos->ejecutar($tipo, $datos['atributos'] ?? []);
        $this->sincronizarMarcaModelo->ejecutar($tipo, $atributos);
        $fechaVencimiento = $datos['fecha_vencimiento']
            ?? $this->validarAtributos->sincronizarFechaVencimiento($tipo, $atributos);

        return DB::transaction(function () use ($usuario, $datos, $tipo, $atributos, $fechaVencimiento) {
            $activo = Activo::create([
                'folio' => $this->generarFolio(),
                'catalogo_tipo_activo_id' => $tipo->id,
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

            $this->registrarMovimiento->ejecutar($activo, $usuario, 'creacion');

            return $activo->load(['tipo', 'departamento', 'area', 'responsable']);
        });
    }

    private function generarFolio(): string
    {
        $year = now()->format('Y');
        $ultimo = Activo::withTrashed()
            ->where('folio', 'like', "ACT-{$year}-%")
            ->orderByDesc('id')
            ->value('folio');

        $numero = 1;
        if ($ultimo && preg_match('/ACT-\d{4}-(\d+)/', $ultimo, $matches)) {
            $numero = (int) $matches[1] + 1;
        }

        return sprintf('ACT-%s-%04d', $year, $numero);
    }
}
