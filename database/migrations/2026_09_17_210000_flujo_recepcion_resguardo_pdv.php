<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_resguardo_bultos', function (Blueprint $table) {
            $table->unsignedInteger('piezas')->default(1)->after('tipo');
            $table->string('condicion', 32)->nullable()->after('piezas');
        });

        DB::table('pdv_resguardos')
            ->where('estado', 'pendiente_custodia')
            ->update(['estado' => 'en_recepcion']);
    }

    public function down(): void
    {
        DB::table('pdv_resguardos')
            ->where('estado', 'en_recepcion')
            ->update(['estado' => 'pendiente_custodia']);

        Schema::table('pdv_resguardo_bultos', function (Blueprint $table) {
            $table->dropColumn(['piezas', 'condicion']);
        });
    }
};
