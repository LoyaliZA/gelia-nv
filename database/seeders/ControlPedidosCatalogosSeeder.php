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
            ['nombre' => 'CAJA #8', 'peso_volumetrico' => 50.6188],
            ['nombre' => 'CAJA #30', 'peso_volumetrico' => 0.9996],
            ['nombre' => 'CAJA #33', 'peso_volumetrico' => 1.35],
            ['nombre' => 'CAJA #349', 'peso_volumetrico' => 1.8522],
            ['nombre' => 'CAJA #28', 'peso_volumetrico' => 1.9964],
            ['nombre' => 'CAJA #31', 'peso_volumetrico' => 2.05],
            ['nombre' => 'CAJA #40', 'peso_volumetrico' => 3.8148],
            ['nombre' => 'CAJA #42', 'peso_volumetrico' => 3.84],
            ['nombre' => 'CAJA #32', 'peso_volumetrico' => 5.1744],
            ['nombre' => 'CAJA #21', 'peso_volumetrico' => 5.39],
            ['nombre' => 'CAJA #56', 'peso_volumetrico' => 7.047],
            ['nombre' => 'CAJA #220', 'peso_volumetrico' => 8.1344],
            ['nombre' => 'CAJA #202', 'peso_volumetrico' => 10.304],
            ['nombre' => 'UNIVERSO #202', 'peso_volumetrico' => 12.144],
            ['nombre' => 'CAJA UNIVERSO', 'peso_volumetrico' => 13.248],
            ['nombre' => 'CAJA UNIVERSO GRANDE', 'peso_volumetrico' => 19.6308],
        ];

        foreach ($tiposCaja as $caja) {
            $existe = DB::table('catalogo_tipos_caja_pedido')->where('nombre', $caja['nombre'])->exists();
            $row = array_merge($caja, ['activo' => true, 'updated_at' => $now]);
            if (!$existe) {
                $row['created_at'] = $now;
            }
            DB::table('catalogo_tipos_caja_pedido')->updateOrInsert(['nombre' => $caja['nombre']], $row);
        }

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
            if (!$existe) {
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
            if (!$existe) {
                $row['created_at'] = $now;
            }
            DB::table('catalogo_paqueterias_pedido')->updateOrInsert(['nombre' => $nombre], $row);
        }
    }
}
