<?php

namespace Database\Seeders;

use App\Models\Atributo;
use App\Models\CatalogoCategoriaProducto;
use App\Models\CategoriaAtributo;
use App\Models\CategoriaExtension;
use App\Models\ExtensionProducto;
use App\Models\NotaOlfativa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Deja lista la personalización de perfumería sobre el maestro universal:
 * categorías, extensión, atributos asignados y catálogo de notas.
 * Idempotente.
 */
class PerfumeriaCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $ext = ExtensionProducto::query()->firstOrCreate(
            ['codigo' => 'perfumeria'],
            [
                'nombre' => 'Perfumería',
                'descripcion' => 'Pirámide olfativa, notas y fases para productos de perfumería',
                'version' => '1',
                'habilitada' => true,
            ]
        );
        if (! $ext->habilitada) {
            $ext->update(['habilitada' => true]);
        }

        $this->asegurarAtributoAnio();

        $padre = CatalogoCategoriaProducto::query()->firstOrCreate(
            ['nombre' => 'Perfumería'],
            [
                'slug' => 'perfumeria',
                'ruta_cache' => 'Perfumería',
                'nivel' => 0,
                'estado' => true,
            ]
        );

        $hijos = [
            'Perfumes',
            'Fragancias corporales',
            'Sets y presentaciones',
        ];
        $categorias = collect([$padre]);
        foreach ($hijos as $nombre) {
            $categorias->push(CatalogoCategoriaProducto::query()->firstOrCreate(
                ['nombre' => $nombre],
                [
                    'parent_id' => $padre->id,
                    'slug' => Str::slug($nombre),
                    'ruta_cache' => 'Perfumería > '.$nombre,
                    'nivel' => 1,
                    'estado' => true,
                ]
            ));
        }

        // Extensión en el padre (heredable) para que subcategorías la reciban.
        CategoriaExtension::query()->updateOrCreate(
            ['categoria_id' => $padre->id, 'extension_id' => $ext->id],
            ['habilitada' => true, 'heredable' => true]
        );

        $slugsAttrs = [
            'volumen',
            'intensidad',
            'publico_objetivo',
            'estacion_recomendada',
            'ocasion_uso',
            'familia_olfativa',
            'concentracion',
            'ano_lanzamiento',
        ];
        $attrs = Atributo::query()->whereIn('slug', $slugsAttrs)->orderBy('nombre')->get();
        foreach ($categorias as $cat) {
            foreach ($attrs->values() as $orden => $attr) {
                CategoriaAtributo::query()->updateOrCreate(
                    ['categoria_id' => $cat->id, 'atributo_id' => $attr->id],
                    [
                        'requerido' => false,
                        'heredable' => true,
                        'orden' => $orden + 1,
                    ]
                );
            }
        }

        foreach ($this->notasBase() as $nombre) {
            $slug = Str::slug($nombre) ?: 'nota-'.Str::random(6);
            NotaOlfativa::query()->firstOrCreate(
                ['slug' => $slug],
                ['nombre' => $nombre, 'estado' => true]
            );
        }
    }

    private function asegurarAtributoAnio(): void
    {
        Atributo::query()->firstOrCreate(
            ['slug' => 'ano_lanzamiento'],
            [
                'nombre' => 'Año de lanzamiento',
                'tipo_dato' => 'entero',
                'permite_multiples' => false,
                'filtrable' => true,
                'buscable' => false,
                'visible_en_ficha' => true,
                'estado' => true,
            ]
        );
    }

    /** @return list<string> */
    private function notasBase(): array
    {
        return [
            // Cítricas / frescas
            'Bergamota', 'Limón', 'Naranja', 'Mandarina', 'Toronja', 'Lima', 'Pomelo',
            // Florales
            'Rosa', 'Jazmín', 'Lavanda', 'Iris', 'Violeta', 'Ylang-ylang', 'Neroli', 'Gardenia', 'Peonía',
            // Amaderadas
            'Cedro', 'Sándalo', 'Vetiver', 'Pachuli', 'Guayaco', 'Pino', 'Oud',
            // Orientales / gourmand
            'Vainilla', 'Ámbar', 'Almizcle', 'Incienso', 'Canela', 'Cardamomo', 'Café', 'Cacao', 'Caramelo', 'Miel',
            // Verdes / aromáticas
            'Menta', 'Albahaca', 'Romero', 'Salvia', 'Té verde', 'Hierba fresca', 'Galbano',
            // Frutales
            'Manzana', 'Pera', 'Durazno', 'Frambuesa', 'Cassis', 'Piña', 'Coco',
            // Acuáticas / otros
            'Notas marinas', 'Ozono', 'Cuero', 'Tabaco', 'Almendra',
        ];
    }
}
