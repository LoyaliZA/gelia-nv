<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            $table->string('consulta_token', 64)->nullable()->unique()->after('folio');
        });

        $activos = DB::table('activos')->whereNull('consulta_token')->pluck('id');

        foreach ($activos as $id) {
            DB::table('activos')->where('id', $id)->update([
                'consulta_token' => (string) Str::uuid(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            $table->dropColumn('consulta_token');
        });
    }
};
