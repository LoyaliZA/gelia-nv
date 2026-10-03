<?php

use App\Support\Catalogos\ClasificacionListaTurno;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->string('tono_sala', 16)->nullable()->after('activo');
            $table->unsignedTinyInteger('prioridad_cola_turnos')->nullable()->after('tono_sala');
        });

        Schema::table('pdv_turnos', function (Blueprint $table) {
            $table->string('lista_tono', 16)->nullable()->after('prioridad_vip');
            $table->unsignedTinyInteger('prioridad_cola')->default(0)->after('lista_tono');
        });

        foreach (DB::table('catalogo_listas_descuento')->get(['id', 'nombre']) as $lista) {
            $clasificacion = ClasificacionListaTurno::resolver($lista->nombre, null, null);
            if ($clasificacion['tono'] === null && $clasificacion['prioridad_cola'] === 0) {
                continue;
            }

            DB::table('catalogo_listas_descuento')->where('id', $lista->id)->update([
                'tono_sala' => $clasificacion['tono'],
                'prioridad_cola_turnos' => $clasificacion['tono'] === 'diamante'
                    ? ClasificacionListaTurno::PRIORIDAD_DIAMANTE_AUTOMATICA
                    : null,
            ]);
        }

        DB::table('pdv_turnos')->where('prioridad_diamante', true)->update([
            'lista_tono' => 'diamante',
            'prioridad_cola' => ClasificacionListaTurno::PRIORIDAD_DIAMANTE_AUTOMATICA,
        ]);
    }

    public function down(): void
    {
        Schema::table('pdv_turnos', function (Blueprint $table) {
            $table->dropColumn(['lista_tono', 'prioridad_cola']);
        });

        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->dropColumn(['tono_sala', 'prioridad_cola_turnos']);
        });
    }
};
