<?php

namespace Database\Factories\PuntoVenta;

use App\Models\PuntoVenta\JornadaPdv;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JornadaPdv>
 */
class JornadaPdvFactory extends Factory
{
    protected $model = JornadaPdv::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sucursal_id' => Sucursal::factory(),
            'estado' => EstadoJornadaPdv::Abierta,
            'apertura_at' => now()->subHour(),
            'version' => 1,
        ];
    }

    public function cerrada(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoJornadaPdv::Cerrada,
            'cierre_at' => now(),
        ]);
    }

    public function cerradaConAtencion(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoJornadaPdv::CerradaConAtencion,
            'cierre_at' => now(),
        ]);
    }
}
