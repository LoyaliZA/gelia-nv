<?php

namespace Database\Factories\PuntoVenta;

use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntervaloOperativoPdv>
 */
class IntervaloOperativoPdvFactory extends Factory
{
    protected $model = IntervaloOperativoPdv::class;

    public function definition(): array
    {
        return [
            'jornada_id' => JornadaPdv::factory(),
            'user_id' => fn (array $attributes) => JornadaPdv::query()->find($attributes['jornada_id'])?->user_id
                ?? JornadaPdv::factory()->create()->user_id,
            'sucursal_id' => fn (array $attributes) => JornadaPdv::query()->find($attributes['jornada_id'])?->sucursal_id
                ?? JornadaPdv::factory()->create()->sucursal_id,
            'tipo' => TipoIntervaloOperativoPdv::Disponible,
            'inicio_at' => now()->subMinutes(30),
            'version' => 1,
        ];
    }

    public function enPausa(): static
    {
        return $this->state(fn (): array => [
            'tipo' => TipoIntervaloOperativoPdv::EnPausa,
        ]);
    }

    public function enAtencion(?TurnoPdvAtencion $atencion = null): static
    {
        return $this->state(fn (): array => [
            'tipo' => TipoIntervaloOperativoPdv::EnAtencion,
            'atencion_id' => $atencion?->id ?? TurnoPdvAtencion::factory(),
        ]);
    }

    public function cerrado(): static
    {
        return $this->state(fn (): array => [
            'fin_at' => now(),
        ]);
    }
}
