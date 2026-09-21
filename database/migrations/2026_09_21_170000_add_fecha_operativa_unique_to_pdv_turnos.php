<?php

use App\Models\PuntoVenta\TurnoPdv;
use App\Services\PuntoVenta\Turnos\TurnosPdvConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_turnos', function (Blueprint $table) {
            $table->date('fecha_operativa')->nullable()->after('folio');
        });

        $config = app(TurnosPdvConfig::class);

        TurnoPdv::query()
            ->select(['id', 'sucursal_id', 'snapshot_json', 'alta_at'])
            ->orderBy('id')
            ->chunkById(200, function ($turnos) use ($config): void {
                foreach ($turnos as $turno) {
                    $snapshot = is_array($turno->snapshot_json) ? $turno->snapshot_json : [];
                    $fechaOperativa = $snapshot['fecha_operativa'] ?? null;

                    if (! is_string($fechaOperativa) || $fechaOperativa === '') {
                        $zona = $config->zonaHorariaOperativa((int) $turno->sucursal_id);
                        $fechaOperativa = $turno->alta_at?->copy()->timezone($zona)->toDateString()
                            ?? now($zona)->toDateString();
                    }

                    DB::table('pdv_turnos')
                        ->where('id', $turno->id)
                        ->update(['fecha_operativa' => $fechaOperativa]);
                }
            });

        Schema::table('pdv_turnos', function (Blueprint $table) {
            $table->date('fecha_operativa')->nullable(false)->change();
            $table->dropUnique('pdv_turnos_sucursal_folio_unique');
            $table->unique(
                ['sucursal_id', 'fecha_operativa', 'folio'],
                'pdv_turnos_sucursal_fecha_folio_unique'
            );
            $table->index(
                ['sucursal_id', 'fecha_operativa', 'estado'],
                'pdv_turnos_sucursal_fecha_estado_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pdv_turnos', function (Blueprint $table) {
            $table->dropIndex('pdv_turnos_sucursal_fecha_estado_idx');
            $table->dropUnique('pdv_turnos_sucursal_fecha_folio_unique');
            $table->unique(['sucursal_id', 'folio'], 'pdv_turnos_sucursal_folio_unique');
            $table->dropColumn('fecha_operativa');
        });
    }
};
