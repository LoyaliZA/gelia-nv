<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('catalogo_tipos_operacion_envio')
            ->where('codigo', 'RESGUARDO_ABIERTO')
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();
        DB::table('catalogo_tipos_operacion_envio')->insert([
            'codigo' => 'RESGUARDO_ABIERTO',
            'nombre' => 'Resguardo abierto',
            'descripcion' => 'Peso, cajas y costo de envío bloqueados hasta la liberación final.',
            'activo' => true,
            'orden' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('catalogo_tipos_operacion_envio')
            ->where('codigo', 'RESGUARDO_ABIERTO')
            ->delete();
    }
};
