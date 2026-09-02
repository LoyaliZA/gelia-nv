<?php

namespace Database\Factories\PuntoVenta;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResguardoPdv>
 */
class ResguardoPdvFactory extends Factory
{
    protected $model = ResguardoPdv::class;

    public function definition(): array
    {
        $folio = 'BMA-'.fake()->unique()->numerify('####');

        return [
            'sucursal_id' => Sucursal::factory(),
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'cantidad_bultos_esperada' => 1,
            'salida_cedis_at' => now(),
            'entrega_bloqueada' => false,
            'snapshot_folio' => $folio,
            'snapshot_json' => ['folio' => $folio],
            'version' => 1,
        ];
    }
}
