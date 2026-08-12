<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('departamento_id')
                ->nullable()
                ->after('area_id')
                ->constrained('departamentos')
                ->nullOnDelete();
        });

        Schema::table('saf_comprobantes_caja', function (Blueprint $table) {
            $table->foreignId('departamento_id')
                ->nullable()
                ->after('generado_por_id')
                ->constrained('departamentos')
                ->nullOnDelete();
            $table->string('logo_key', 64)->nullable()->after('departamento_id');
        });

        // Backfill: un solo departamento asignado → principal.
        $pares = DB::table('departamento_user')
            ->select('user_id', DB::raw('MIN(departamento_id) as departamento_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->having('total', '=', 1)
            ->get();

        foreach ($pares as $par) {
            DB::table('users')
                ->where('id', $par->user_id)
                ->whereNull('departamento_id')
                ->update(['departamento_id' => $par->departamento_id]);
        }
    }

    public function down(): void
    {
        Schema::table('saf_comprobantes_caja', function (Blueprint $table) {
            $table->dropForeign(['departamento_id']);
            $table->dropColumn(['departamento_id', 'logo_key']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['departamento_id']);
            $table->dropColumn('departamento_id');
        });
    }
};
