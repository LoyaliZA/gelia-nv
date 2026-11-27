<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALFABETO = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LONGITUD = 6;

    public function up(): void
    {
        Schema::table('pdv_pantalla_sala_tokens', function (Blueprint $table) {
            $table->string('codigo_corto', 8)->nullable()->unique()->after('token_publico');
        });

        $usados = [];
        $filas = DB::table('pdv_pantalla_sala_tokens')
            ->whereNull('codigo_corto')
            ->orderBy('id')
            ->get(['id']);

        foreach ($filas as $fila) {
            $codigo = $this->generarCodigo($usados);
            $usados[$codigo] = true;
            DB::table('pdv_pantalla_sala_tokens')
                ->where('id', $fila->id)
                ->update(['codigo_corto' => $codigo]);
        }
    }

    public function down(): void
    {
        Schema::table('pdv_pantalla_sala_tokens', function (Blueprint $table) {
            $table->dropUnique(['codigo_corto']);
            $table->dropColumn('codigo_corto');
        });
    }

    /**
     * @param  array<string, true>  $usados
     */
    private function generarCodigo(array $usados): string
    {
        $max = strlen(self::ALFABETO) - 1;

        for ($intento = 0; $intento < 40; $intento++) {
            $codigo = '';
            for ($i = 0; $i < self::LONGITUD; $i++) {
                $codigo .= self::ALFABETO[random_int(0, $max)];
            }

            if (! isset($usados[$codigo])) {
                return $codigo;
            }
        }

        throw new RuntimeException('No se pudo generar un código corto único para pantalla de sala.');
    }
};
