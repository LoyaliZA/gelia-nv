<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->string('codigo', 6)->nullable()->unique()->after('nombre');
        });

        $departamentos = DB::table('departamentos')->get(['id', 'nombre']);

        foreach ($departamentos as $departamento) {
            DB::table('departamentos')->where('id', $departamento->id)->update([
                'codigo' => $this->derivarCodigo($departamento->nombre),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });
    }

    private function derivarCodigo(string $nombre): string
    {
        $palabras = preg_split('/\s+/', trim($nombre)) ?: [];

        if (count($palabras) >= 2) {
            $iniciales = '';
            foreach ($palabras as $palabra) {
                $iniciales .= strtoupper(substr($palabra, 0, 1));
            }

            return substr($iniciales, 0, 6) ?: 'GEN';
        }

        $limpio = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nombre));

        return substr($limpio, 0, 3) ?: 'GEN';
    }
};
