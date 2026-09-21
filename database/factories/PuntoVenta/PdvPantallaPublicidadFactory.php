<?php

namespace Database\Factories\PuntoVenta;

use App\Models\PuntoVenta\PdvPantallaPublicidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdvPantallaPublicidad>
 */
class PdvPantallaPublicidadFactory extends Factory
{
    protected $model = PdvPantallaPublicidad::class;

    public function definition(): array
    {
        return [
            'sucursal_id' => null,
            'tipo' => PdvPantallaPublicidad::TIPO_IMAGEN,
            'ruta' => 'pdv/pantalla-publicidad/ejemplo.jpg',
            'duracion_seg' => 8,
            'ajuste' => PdvPantallaPublicidad::AJUSTE_COVER,
            'orden' => 0,
            'activa' => true,
            'vigente_desde' => null,
            'vigente_hasta' => null,
            'nombre_original' => 'ejemplo.jpg',
        ];
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'tipo' => PdvPantallaPublicidad::TIPO_VIDEO,
            'ruta' => 'pdv/pantalla-publicidad/ejemplo.mp4',
            'duracion_seg' => null,
            'nombre_original' => 'ejemplo.mp4',
        ]);
    }
}
