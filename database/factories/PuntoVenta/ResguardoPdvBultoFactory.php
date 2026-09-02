<?php

namespace Database\Factories\PuntoVenta;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResguardoPdvBulto>
 */
class ResguardoPdvBultoFactory extends Factory
{
    protected $model = ResguardoPdvBulto::class;

    public function definition(): array
    {
        return [
            'resguardo_id' => ResguardoPdv::factory(),
            'tipo' => ResguardoPdvBulto::TIPO_CAJA,
            'estado' => ResguardoPdvBulto::ESTADO_ESPERADO,
            'version' => 1,
        ];
    }
}
