<?php

namespace Database\Factories\PuntoVenta;

use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnoPdvAtencion>
 */
class TurnoPdvAtencionFactory extends Factory
{
    protected $model = TurnoPdvAtencion::class;

    public function definition(): array
    {
        return [
            'turno_id' => TurnoPdv::factory(),
            'user_id' => User::factory(),
            'numero_secuencia' => 1,
            'inicio_at' => now(),
            'es_transferencia' => false,
            'version' => 1,
        ];
    }
}
