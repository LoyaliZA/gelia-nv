<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->decimal('porcentaje_descuento', 5, 2)->default(0)->after('monto_requerido');
        });

        $defaults = [
            'BRONCE' => 0.00,
            'PLATA' => 2.00,
            'ORO' => 4.00,
            'DIAMANTE' => 6.00,
        ];

        foreach (DB::table('catalogo_listas_descuento')->get(['id', 'nombre']) as $lista) {
            $nombreUpper = strtoupper($lista->nombre);
            if (str_contains($nombreUpper, 'COLABORADOR') || str_contains($nombreUpper, 'PLATAFORMAS')) {
                continue;
            }

            $pct = 0.0;
            foreach ($defaults as $keyword => $value) {
                if (str_contains($nombreUpper, $keyword)) {
                    $pct = $value;
                    break;
                }
            }

            DB::table('catalogo_listas_descuento')->where('id', $lista->id)->update([
                'porcentaje_descuento' => $pct,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->dropColumn('porcentaje_descuento');
        });
    }
};
