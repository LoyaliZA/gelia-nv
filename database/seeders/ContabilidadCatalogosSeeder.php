<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContabilidadCatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $estatus = [
            ['id' => 1, 'codigo' => 'pendiente', 'nombre' => 'Pendiente'],
            ['id' => 2, 'codigo' => 'transferido', 'nombre' => 'Transferido'],
        ];

        $tipos = [
            ['id' => 1, 'codigo' => 'venta', 'nombre' => 'Venta'],
            ['id' => 2, 'codigo' => 'contracargo', 'nombre' => 'Contracargo'],
            ['id' => 3, 'codigo' => 'reembolso', 'nombre' => 'Reembolso'],
        ];

        $frecuencias = [
            ['id' => 1, 'codigo' => 'inmediato', 'nombre' => 'Inmediato'],
            ['id' => 2, 'codigo' => 'diario', 'nombre' => 'Diario'],
            ['id' => 3, 'codigo' => 'semanal', 'nombre' => 'Semanal'],
            ['id' => 4, 'codigo' => 'quincenal', 'nombre' => 'Quincenal'],
            ['id' => 5, 'codigo' => 'personalizado', 'nombre' => 'Personalizado'],
        ];

        foreach ($estatus as $row) {
            DB::table('contabilidad_catalogo_estatus_pago')->updateOrInsert(
                ['id' => $row['id']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        foreach ($tipos as $row) {
            DB::table('contabilidad_catalogo_tipos_transaccion')->updateOrInsert(
                ['id' => $row['id']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        foreach ($frecuencias as $row) {
            DB::table('contabilidad_catalogo_frecuencia_pago')->updateOrInsert(
                ['id' => $row['id']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
