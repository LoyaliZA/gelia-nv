<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ControlPedidosCatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $tiposCaja = [
            ['nombre' => 'CAJA #30', 'largo' => 21, 'ancho' => 17, 'alto' => 14, 'peso_volumetrico' => 0.9996],
            ['nombre' => 'CAJA #28', 'largo' => 31, 'ancho' => 23, 'alto' => 14, 'peso_volumetrico' => 1.9964],
            ['nombre' => 'CAJA #42', 'largo' => 32, 'ancho' => 25, 'alto' => 24, 'peso_volumetrico' => 3.84],
            ['nombre' => 'CAJA #56', 'largo' => 45, 'ancho' => 29, 'alto' => 27, 'peso_volumetrico' => 7.047],
            ['nombre' => 'CAJA #220', 'largo' => 41, 'ancho' => 31, 'alto' => 32, 'peso_volumetrico' => 8.1344],
            ['nombre' => 'CAJA #202', 'largo' => 46, 'ancho' => 32, 'alto' => 35, 'peso_volumetrico' => 10.304],
            ['nombre' => 'CAJA #21', 'largo' => 35, 'ancho' => 35, 'alto' => 22, 'peso_volumetrico' => 5.39],
            ['nombre' => 'CAJA UNIVERSO GRANDE', 'largo' => 57, 'ancho' => 41, 'alto' => 42, 'peso_volumetrico' => 19.6308],
            ['nombre' => 'UNIVERSO #202', 'largo' => 46, 'ancho' => 33, 'alto' => 40, 'peso_volumetrico' => 12.144],
            ['nombre' => 'CAJA #40', 'largo' => 34, 'ancho' => 33, 'alto' => 17, 'peso_volumetrico' => 3.8148],
            ['nombre' => 'CAJA #349', 'largo' => 21, 'ancho' => 21, 'alto' => 21, 'peso_volumetrico' => 1.8522],
            ['nombre' => 'CAJA #85', 'largo' => 17, 'ancho' => 14, 'alto' => 13, 'peso_volumetrico' => 0.6188],
            ['nombre' => 'CAJA #32', 'largo' => 28, 'ancho' => 22, 'alto' => 42, 'peso_volumetrico' => 5.1744],
            ['nombre' => 'CAJA #33', 'largo' => 30, 'ancho' => 15, 'alto' => 15, 'peso_volumetrico' => 1.35],
            ['nombre' => 'CAJA #31', 'largo' => 31, 'ancho' => 22, 'alto' => 15, 'peso_volumetrico' => 2.05],
        ];

        foreach ($tiposCaja as $caja) {
            $existe = DB::table('catalogo_tipos_caja_pedido')->where('nombre', $caja['nombre'])->exists();
            $row = [
                'peso_volumetrico' => $caja['peso_volumetrico'],
                'largo' => $caja['largo'],
                'ancho' => $caja['ancho'],
                'alto' => $caja['alto'],
                'medidas' => "{$caja['largo']} x {$caja['ancho']} x {$caja['alto']} cm",
                'activo' => true,
                'updated_at' => $now,
            ];
            if (! $existe) {
                $row['created_at'] = $now;
            }
            DB::table('catalogo_tipos_caja_pedido')->updateOrInsert(['nombre' => $caja['nombre']], $row);
        }

        DB::table('catalogo_tipos_caja_pedido')
            ->where('nombre', 'CAJA #8')
            ->update(['activo' => false, 'updated_at' => $now]);

        $paqueteriasComerciales = ['FEDEX', 'ESTAFETA', 'DHL'];

        $paqueteriasLocales = [
            'TAXI FRONTERA',
            'TAXI MACUSPANA',
            'TAXI REFORMA',
            'TAXI CD PEMEX',
            'TAXI AGUILAS TUXTLA G.',
            'TAXI NAXAJUCA',
            'TAXI TACOTALPA',
            'TAXI HUIMANGUILLO',
            'VAN JALPA DE MENDEZ',
            'VAN CUNDUACAN',
            'VAN EJECUTIVA PARAISO',
            'MTP PARAISO',
            'COMALLI / COMALCALCO',
            'SULTANA TEAPA',
            'SULTANA PICHUCALCO',
            'T. JAGUAR PALENQUE',
            'T. JAGUAR TENOSIQUE',
            'T. JAGUAR BALANCAN',
            'T. JAGUAR EMILIANO ZAPATA',
            'TRANSPORTE NIÑOS TRAVIESOS TNT',
            'ENVIO CARDESA',
            'OTRA',
        ];

        foreach ($paqueteriasComerciales as $nombre) {
            $existe = DB::table('catalogo_paqueterias_pedido')->where('nombre', $nombre)->exists();
            $row = [
                'categoria' => 'comercial',
                'activo' => true,
                'updated_at' => $now,
            ];
            if (! $existe) {
                $row['created_at'] = $now;
            }
            DB::table('catalogo_paqueterias_pedido')->updateOrInsert(['nombre' => $nombre], $row);
        }

        foreach ($paqueteriasLocales as $nombre) {
            $existe = DB::table('catalogo_paqueterias_pedido')->where('nombre', $nombre)->exists();
            $row = [
                'categoria' => 'local_regional',
                'activo' => true,
                'updated_at' => $now,
            ];
            if (! $existe) {
                $row['created_at'] = $now;
            }
            DB::table('catalogo_paqueterias_pedido')->updateOrInsert(['nombre' => $nombre], $row);
        }
    }
};
