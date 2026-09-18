<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SETTINGS = [
        'pct_tiendanube' => '17.65',
        'pct_tiendanubemayoreo' => '22',
    ];

    private const LISTAS_LISTADO = [
        'Lista TiendaNube' => 17.65,
        'TiendaNube Mayoreo' => 22.00,
    ];

    public function up(): void
    {
        if (Schema::hasTable('gelia_settings')) {
            foreach (self::SETTINGS as $key => $value) {
                if (DB::table('gelia_settings')->where('key', $key)->exists()) {
                    continue;
                }

                DB::table('gelia_settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (!Schema::hasTable('catalogo_listas_descuento')
            || !Schema::hasTable('catalogo_porcentajes_listado_lista')) {
            return;
        }

        foreach (self::LISTAS_LISTADO as $nombre => $porcentaje) {
            $listaId = DB::table('catalogo_listas_descuento')->where('nombre', $nombre)->value('id');

            if (!$listaId) {
                $listaId = DB::table('catalogo_listas_descuento')->insertGetId([
                    'nombre' => $nombre,
                    'monto_requerido' => 0,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('catalogo_porcentajes_listado_lista')->updateOrInsert(
                ['catalogo_lista_descuento_id' => $listaId],
                [
                    'porcentaje_descuento' => $porcentaje,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gelia_settings')) {
            DB::table('gelia_settings')->whereIn('key', array_keys(self::SETTINGS))->delete();
        }
    }
};
