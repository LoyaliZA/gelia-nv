<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (['Envío', 'Tienda'] as $nombre) {
            $existe = DB::table('catalogo_envios_tienda')->where('nombre', $nombre)->exists();
            if ($existe) {
                continue;
            }

            DB::table('catalogo_envios_tienda')->insert([
                'nombre' => $nombre,
                'es_otro' => false,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No elimina filas: pueden estar referenciadas por pedidos existentes.
    }
};
