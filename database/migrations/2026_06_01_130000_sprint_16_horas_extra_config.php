<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_configuraciones', function (Blueprint $table) {
            $table->decimal('he_tarifa_hora_fija', 10, 2)->default(39.00)->after('he_minutos_minimos');
            $table->boolean('he_usar_tarifa_fija')->default(true)->after('he_tarifa_hora_fija');
            $table->unsignedSmallInteger('he_gracia_minutos_despues_salida')->default(30)->after('he_usar_tarifa_fija');
        });

        Schema::table('rh_colaboradores', function (Blueprint $table) {
            $table->time('hora_entrada_oficial')->nullable()->after('horas_laboradas_oficiales');
            $table->time('hora_salida_oficial')->nullable()->after('hora_entrada_oficial');
        });

        Schema::table('rh_horas_extra', function (Blueprint $table) {
            $table->decimal('monto_horas_extra', 12, 2)->default(0)->after('total_economico');
            $table->decimal('tarifa_hora_snapshot', 10, 4)->nullable()->after('multiplicador_snapshot');
        });

        if (Schema::hasColumn('rh_horas_extra', 'total_economico')) {
            \Illuminate\Support\Facades\DB::table('rh_horas_extra')
                ->where('monto_horas_extra', 0)
                ->update(['monto_horas_extra' => \Illuminate\Support\Facades\DB::raw('total_economico')]);
        }
    }

    public function down(): void
    {
        Schema::table('rh_horas_extra', function (Blueprint $table) {
            $table->dropColumn(['monto_horas_extra', 'tarifa_hora_snapshot']);
        });

        Schema::table('rh_colaboradores', function (Blueprint $table) {
            $table->dropColumn(['hora_entrada_oficial', 'hora_salida_oficial']);
        });

        Schema::table('rh_configuraciones', function (Blueprint $table) {
            $table->dropColumn(['he_tarifa_hora_fija', 'he_usar_tarifa_fija', 'he_gracia_minutos_despues_salida']);
        });
    }
};
