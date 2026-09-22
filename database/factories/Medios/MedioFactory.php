<?php

namespace Database\Factories\Medios;

use App\Models\Medios\Medio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Medio>
 */
class MedioFactory extends Factory
{
    protected $model = Medio::class;

    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'nombre_original' => 'promo.jpg',
            'object_key' => 'advertising/media/2026/09/'.$uuid.'/promo.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'tamano_bytes' => 12000,
            'duracion_seg' => 10,
            'tipo' => Medio::TIPO_IMAGEN,
            'estado' => Medio::ESTADO_READY,
            'proposito' => Medio::PROPOSITO_PDV_PUBLICIDAD,
        ];
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'nombre_original' => 'promo.mp4',
            'object_key' => 'advertising/media/2026/09/'.Str::uuid().'/promo.mp4',
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'tamano_bytes' => 5_000_000,
            'duracion_seg' => 154,
            'tipo' => Medio::TIPO_VIDEO,
        ]);
    }
}
