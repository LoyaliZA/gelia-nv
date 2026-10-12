<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->boolean('visible_en_solicitudes')->default(false)->after('participa_escalonamiento');
        });

        DB::table('catalogo_listas_descuento')
            ->whereRaw('UPPER(nombre) IN (?, ?, ?, ?, ?)', [
                'PUBLICO GENERAL',
                'MAYOREO BRONCE',
                'MAYOREO PLATA',
                'MAYOREO ORO',
                'MAYOREO DIAMANTE',
            ])
            ->update(['visible_en_solicitudes' => true]);
    }

    public function down(): void
    {
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->dropColumn('visible_en_solicitudes');
        });
    }
};
