<?php

namespace Database\Factories\PuntoVenta;

use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SucursalDiaOperacionPdv>
 */
class SucursalDiaOperacionPdvFactory extends Factory
{
    protected $model = SucursalDiaOperacionPdv::class;

    public function definition(): array
    {
        return [
            'sucursal_id' => Sucursal::factory(),
            'fecha_operativa' => now()->toDateString(),
            'hora_cierre' => '19:00:00',
            'acepta_altas' => true,
            'cierre_automatico_invalidado' => false,
            'version' => 1,
        ];
    }

    public function sinAltas(): static
    {
        return $this->state(fn (): array => [
            'acepta_altas' => false,
        ]);
    }
}
