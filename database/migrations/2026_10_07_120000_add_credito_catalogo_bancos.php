<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $existente = DB::table('catalogo_bancos')->where('nombre', 'Crédito')->exists();

        if ($existente) {
            DB::table('catalogo_bancos')
                ->where('nombre', 'Crédito')
                ->update(['activo' => true, 'updated_at' => $now]);

            return;
        }

        DB::table('catalogo_bancos')->insert([
            'nombre' => 'Crédito',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $bancoId = DB::table('catalogo_bancos')->where('nombre', 'Crédito')->value('id');

        if (! $bancoId) {
            return;
        }

        $enUso = DB::table('solicitudes_tags')->where('catalogo_banco_id', $bancoId)->exists();

        if ($enUso) {
            DB::table('catalogo_bancos')
                ->where('id', $bancoId)
                ->update(['activo' => false, 'updated_at' => now()]);

            return;
        }

        DB::table('catalogo_bancos')->where('id', $bancoId)->delete();
    }
};
