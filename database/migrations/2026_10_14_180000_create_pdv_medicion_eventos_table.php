<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('La bitácora PDV requiere MySQL o SQLite de pruebas.');
        }
        Schema::create('pdv_medicion_eventos', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->uuid('evento_uuid')->unique('pdv_med_uuid_uq');
            $table->unsignedSmallInteger('esquema_version');
            $table->unsignedSmallInteger('contrato_version');
            $table->string('tipo', 64);
            $table->string('agregado_tipo', 16);
            $table->unsignedBigInteger('agregado_id');
            $table->unsignedBigInteger('secuencia');
            foreach (['user_id' => 'users', 'actor_id' => 'users', 'sucursal_id' => 'sucursales',
                'jornada_id' => 'pdv_jornadas', 'turno_id' => 'pdv_turnos', 'atencion_id' => 'pdv_turno_atenciones',
                'intervalo_id' => 'pdv_intervalos_operativos', 'motivo_pausa_id' => 'pdv_motivos_pausa',
                'configuracion_id' => 'configuraciones_sistema'] as $columna => $tabla) {
                $table->foreignId($columna)->nullable()->constrained($tabla)->restrictOnDelete();
            }
            $table->string('actor_tipo', 16);
            $table->uuid('sesion_uuid')->nullable();
            $table->string('origen', 32);
            $table->dateTime('efectivo_at', 6);
            $table->dateTime('registrado_at', 6)->default(DB::raw($driver === 'mysql' ? '(UTC_TIMESTAMP(6))' : 'CURRENT_TIMESTAMP'));
            $table->string('zona_horaria', 64)->nullable();
            $table->date('fecha_operativa')->nullable();
            $table->json('estado_anterior')->nullable();
            $table->json('estado_nuevo')->nullable();
            $table->char('idempotency_key', 64)->unique('pdv_med_idem_uq');
            $table->char('comando_hash', 64);
            $table->uuid('correlacion_uuid');
            $table->foreignId('causado_por_id')->nullable()->constrained('pdv_medicion_eventos')->restrictOnDelete();
            $table->foreignId('corrige_evento_id')->nullable()->constrained('pdv_medicion_eventos')->restrictOnDelete();
            $table->json('contexto');
            $table->unique(['agregado_tipo', 'agregado_id', 'secuencia'], 'pdv_med_secuencia_uq');
            $table->index(['user_id', 'efectivo_at', 'id'], 'pdv_med_user_ef_idx');
            $table->index(['sucursal_id', 'efectivo_at', 'id'], 'pdv_med_suc_ef_idx');
            $table->index(['registrado_at', 'id'], 'pdv_med_reg_idx');
            $table->index(['jornada_id', 'efectivo_at', 'id'], 'pdv_med_jornada_idx');
            $table->index('correlacion_uuid', 'pdv_med_correlacion_idx');
        });

        $checks = [
            'pdv_med_version_ck' => 'secuencia > 0 AND esquema_version > 0 AND contrato_version > 0',
            'pdv_med_actor_ck' => "(actor_tipo = 'usuario' AND actor_id IS NOT NULL) OR (actor_tipo = 'sistema' AND actor_id IS NULL)",
            'pdv_med_agregado_ck' => "(agregado_tipo = 'persona' AND user_id IS NOT NULL AND agregado_id = user_id AND sucursal_id IS NOT NULL) OR (agregado_tipo = 'sucursal' AND sucursal_id IS NOT NULL AND agregado_id = sucursal_id AND user_id IS NULL) OR (agregado_tipo = 'configuracion' AND configuracion_id IS NOT NULL AND agregado_id = configuracion_id AND user_id IS NULL)",
        ];
        if ($driver === 'mysql') {
            foreach ($checks as $nombre => $expresion) {
                DB::statement("ALTER TABLE pdv_medicion_eventos ADD CONSTRAINT {$nombre} CHECK ({$expresion})");
            }
        } else {
            foreach ($checks as $nombre => $expresion) {
                $condicion = preg_replace('/\b(secuencia|esquema_version|contrato_version|actor_tipo|actor_id|agregado_tipo|user_id|agregado_id|sucursal_id|configuracion_id)\b/', 'NEW.$1', $expresion);
                DB::unprepared("CREATE TRIGGER {$nombre} BEFORE INSERT ON pdv_medicion_eventos WHEN NOT ({$condicion}) BEGIN SELECT RAISE(ABORT, 'Evento PDV no válido'); END");
            }
        }
        foreach (['UPDATE', 'DELETE'] as $operacion) {
            $nombre = 'pdv_med_no_'.strtolower($operacion);
            DB::unprepared($driver === 'mysql'
                ? "CREATE TRIGGER {$nombre} BEFORE {$operacion} ON pdv_medicion_eventos FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Evento PDV inmutable'"
                : "CREATE TRIGGER {$nombre} BEFORE {$operacion} ON pdv_medicion_eventos BEGIN SELECT RAISE(ABORT, 'Evento PDV inmutable'); END");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pdv_medicion_eventos') && DB::table('pdv_medicion_eventos')->exists()) {
            throw new RuntimeException('No se elimina una bitácora PDV con evidencia.');
        }
        Schema::dropIfExists('pdv_medicion_eventos');
    }
};
