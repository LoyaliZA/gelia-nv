<?php

namespace Database\Factories\PuntoVenta;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnoPdv>
 */
class TurnoPdvFactory extends Factory
{
    protected $model = TurnoPdv::class;

    public function definition(): array
    {
        $folio = 'V-'.fake()->unique()->numerify('####');

        return [
            'sucursal_id' => Sucursal::factory(),
            'folio' => $folio,
            'servicio' => TurnoPdv::SERVICIO_VENTAS,
            'origen' => TurnoPdv::ORIGEN_RECEPCION,
            'estado' => TurnoPdv::ESTADO_EN_COLA,
            'prioridad' => TurnoPdv::PRIORIDAD_NORMAL,
            'snapshot_nombre_llamado' => fake()->firstName().' '.fake()->lastName(),
            'snapshot_json' => ['folio' => $folio],
            'alta_at' => now(),
            'version' => 1,
        ];
    }

    public function visitante(): static
    {
        return $this->state(fn () => [
            'cliente_id' => null,
            'snapshot_cliente_nombre' => null,
        ]);
    }
}
