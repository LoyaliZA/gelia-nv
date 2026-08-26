<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $existe = DB::table('catalogo_paqueterias_pedido')
            ->where('nombre', 'PAQ. PENDIENTE')
            ->exists();

        $row = [
            'categoria' => 'local_regional',
            'permite_costo_diferido' => true,
            'activo' => true,
            'updated_at' => $now,
        ];
        if (! $existe) {
            $row['created_at'] = $now;
        }

        DB::table('catalogo_paqueterias_pedido')->updateOrInsert(
            ['nombre' => 'PAQ. PENDIENTE'],
            $row
        );
    }

    public function down(): void
    {
        DB::table('catalogo_paqueterias_pedido')
            ->where('nombre', 'PAQ. PENDIENTE')
            ->delete();
    }
};
