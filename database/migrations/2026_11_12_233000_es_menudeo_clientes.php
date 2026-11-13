<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clientes', 'es_menudeo')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->boolean('es_menudeo')->default(false)->after('es_inactivo');
            });
        }

        if (Schema::hasColumn('clientes', 'modalidad_comercial')) {
            DB::table('clientes')->where('modalidad_comercial', 'menudeo')->update(['es_menudeo' => true]);
        }

        if (Schema::hasTable('cliente_modalidad_eventos') && ! Schema::hasColumn('cliente_modalidad_eventos', 'es_menudeo')) {
            Schema::table('cliente_modalidad_eventos', function (Blueprint $table) {
                $table->boolean('es_menudeo')->default(false)->after('cliente_id');
            });
            if (Schema::hasColumn('cliente_modalidad_eventos', 'modalidad')) {
                DB::table('cliente_modalidad_eventos')->where('modalidad', 'menudeo')->update(['es_menudeo' => true]);
            }
        }

        if (Schema::hasTable('solicitud_beneficios') && Schema::hasColumn('solicitud_beneficios', 'campo')) {
            foreach (['valor_anterior', 'valor_aplicado'] as $columna) {
                DB::table('solicitud_beneficios')->where('campo', 'modalidad')->where($columna, 'menudeo')->update([$columna => '1']);
                DB::table('solicitud_beneficios')->where('campo', 'modalidad')->where($columna, 'mayoreo')->update([$columna => '0']);
            }
            DB::table('solicitud_beneficios')->where('campo', 'modalidad')->update(['campo' => 'es_menudeo']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cliente_modalidad_eventos', 'es_menudeo')) {
            Schema::table('cliente_modalidad_eventos', function (Blueprint $table) {
                $table->dropColumn('es_menudeo');
            });
        }

        if (Schema::hasColumn('clientes', 'es_menudeo')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('es_menudeo');
            });
        }
    }
};
