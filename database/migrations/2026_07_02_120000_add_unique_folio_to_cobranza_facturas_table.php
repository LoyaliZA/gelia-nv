<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicados = DB::table('cobranza_facturas')
            ->select('folio')
            ->groupBy('folio')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('folio');

        foreach ($duplicados as $folio) {
            $ids = DB::table('cobranza_facturas')
                ->where('folio', $folio)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids->slice(1) as $id) {
                DB::table('cobranza_facturas')
                    ->where('id', $id)
                    ->update(['folio' => $folio . '-dup-' . $id]);
            }
        }

        $indexes = collect(Schema::getIndexes('cobranza_facturas'));
        $tieneIndiceFolio = $indexes->contains(
            fn (array $indice) => in_array('folio', $indice['columns'] ?? [], true) && !($indice['unique'] ?? false)
        );
        $tieneUniqueFolio = $indexes->contains(
            fn (array $indice) => in_array('folio', $indice['columns'] ?? [], true) && ($indice['unique'] ?? false)
        );

        Schema::table('cobranza_facturas', function (Blueprint $table) use ($tieneIndiceFolio, $tieneUniqueFolio) {
            if ($tieneIndiceFolio) {
                $table->dropIndex(['folio']);
            }

            if (!$tieneUniqueFolio) {
                $table->unique('folio');
            }
        });
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('cobranza_facturas'));
        $tieneUniqueFolio = $indexes->contains(
            fn (array $indice) => in_array('folio', $indice['columns'] ?? [], true) && ($indice['unique'] ?? false)
        );

        Schema::table('cobranza_facturas', function (Blueprint $table) use ($tieneUniqueFolio) {
            if ($tieneUniqueFolio) {
                $table->dropUnique(['folio']);
            }

            $table->index('folio');
        });
    }
};
