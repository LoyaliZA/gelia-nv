<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escalonamiento_conciliaciones_solicitud', function (Blueprint $table) {
            $table->unsignedBigInteger('escalonamiento_periodo_id')->nullable()->change();
            $table->string('procedencia', 40)->nullable()->after('estado');
            $table->string('inclusion_acumulado', 20)->nullable()->after('procedencia');
            $table->string('clave_asignacion', 80)->nullable()->after('inclusion_acumulado');
        });

        DB::table('escalonamiento_conciliaciones_solicitud')
            ->select(['id', 'documento_venta_id'])
            ->orderBy('id')
            ->chunkById(200, function ($filas): void {
                foreach ($filas as $fila) {
                    DB::table('escalonamiento_conciliaciones_solicitud')->where('id', $fila->id)->update([
                        'clave_asignacion' => $fila->documento_venta_id
                            ? 'doc:'.$fila->documento_venta_id
                            : 'sin-documento:'.$fila->id,
                        'procedencia' => $fila->documento_venta_id ? 'documento' : 'identidad_insuficiente',
                        'inclusion_acumulado' => 'indeterminada',
                    ]);
                }
            });

        Schema::table('escalonamiento_conciliaciones_solicitud', function (Blueprint $table) {
            $table->string('clave_asignacion', 80)->nullable(false)->change();
            $table->unique(['solicitud_tag_id', 'clave_asignacion'], 'esc_conc_sol_clave_unica');
        });
    }

    public function down(): void
    {
        Schema::table('escalonamiento_conciliaciones_solicitud', function (Blueprint $table) {
            $table->dropUnique('esc_conc_sol_clave_unica');
            $table->dropColumn(['clave_asignacion', 'procedencia', 'inclusion_acumulado']);
        });

        DB::table('escalonamiento_conciliaciones_solicitud')->whereNull('escalonamiento_periodo_id')->delete();

        Schema::table('escalonamiento_conciliaciones_solicitud', function (Blueprint $table) {
            $table->unsignedBigInteger('escalonamiento_periodo_id')->nullable(false)->change();
        });
    }
};
