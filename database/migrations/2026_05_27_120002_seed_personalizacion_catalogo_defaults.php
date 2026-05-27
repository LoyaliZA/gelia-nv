<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('personalizacion_tonos')->count() > 0) {
            return;
        }

        DB::table('personalizacion_tonos')->insert([
            'slug'       => 'default',
            'nombre'     => 'Campana clásica',
            'archivo'    => 'notification.mp3',
            'activo'     => true,
            'orden'      => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fondosVectoriales = [
            ['slug' => 'blob',       'nombre' => 'Blob'],
            ['slug' => 'blobscene',  'nombre' => 'Blob Scene'],
            ['slug' => 'circle',     'nombre' => 'Circle'],
            ['slug' => 'layered',    'nombre' => 'Layered'],
            ['slug' => 'peaks',      'nombre' => 'Peaks'],
            ['slug' => 'polygon',    'nombre' => 'Polygon'],
            ['slug' => 'square',     'nombre' => 'Square'],
            ['slug' => 'stacked',    'nombre' => 'Stacked'],
            ['slug' => 'steps',      'nombre' => 'Steps'],
            ['slug' => 'wave',       'nombre' => 'Wave'],
        ];

        foreach ($fondosVectoriales as $i => $fondo) {
            DB::table('personalizacion_fondos')->insert([
                'slug'       => $fondo['slug'],
                'nombre'     => $fondo['nombre'],
                'tipo'       => 'vector',
                'valor'      => $fondo['slug'],
                'activo'     => true,
                'orden'      => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $temas = [
            [
                'slug' => 'gelia-signature',
                'nombre' => 'Gelia Signature',
                'configuracion' => json_encode([
                    'modo' => 'dark',
                    'color_nombre' => 'rosa',
                    'color_hex' => '#ec4899',
                    'fondo_base' => 'blob',
                    'fuente_principal' => 'montserrat',
                    'escala_fuente' => 1,
                    'layout_sidebar' => 'floating_left',
                    'efecto_cristal' => true,
                    'sonido' => true,
                ]),
                'orden' => 0,
            ],
            [
                'slug' => 'gelia-oasis',
                'nombre' => 'GELIA Oasis',
                'configuracion' => json_encode([
                    'modo' => 'light',
                    'color_nombre' => 'verde',
                    'color_hex' => '#10b981',
                    'fondo_base' => 'stacked',
                    'fuente_principal' => 'poppins',
                    'escala_fuente' => 1,
                    'layout_sidebar' => 'floating_right',
                    'efecto_cristal' => false,
                    'sonido' => true,
                ]),
                'orden' => 1,
            ],
            [
                'slug' => 'cybertech',
                'nombre' => 'CyberTech',
                'configuracion' => json_encode([
                    'modo' => 'dark',
                    'color_nombre' => 'azul',
                    'color_hex' => '#3b82f6',
                    'fondo_base' => 'polygon',
                    'fuente_principal' => 'mono',
                    'escala_fuente' => 1,
                    'layout_sidebar' => 'fixed',
                    'efecto_cristal' => false,
                    'sonido' => true,
                ]),
                'orden' => 2,
            ],
        ];

        foreach ($temas as $tema) {
            DB::table('personalizacion_temas')->insert([
                'slug'          => $tema['slug'],
                'nombre'        => $tema['nombre'],
                'configuracion' => $tema['configuracion'],
                'activo'        => true,
                'orden'         => $tema['orden'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('personalizacion_temas')->truncate();
        DB::table('personalizacion_fondos')->truncate();
        DB::table('personalizacion_tonos')->truncate();
    }
};
