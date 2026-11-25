<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FUENTES = [
        'pedidos_bma' => ['pedido' => 'id'],
        'pedido_bma_cajas' => ['pedido' => 'pedido_bma_id'],
        'pedido_bma_bultos_empaque' => ['pedido' => 'pedido_bma_id'],
        'pedido_bma_historial_estados' => ['pedido' => 'pedido_bma_id'],
        'pedido_bma_caja_remisiones' => ['caja' => 'pedido_bma_caja_id', 'pedido' => 'pedido_bma_id'],
        'pedido_bma_tareas_preparacion' => ['pedido' => 'pedido_bma_id'],
        'pedido_bma_cumplimiento_fisico' => ['tarea' => 'pedido_bma_tarea_preparacion_id'],
        'operacion_empaque_miembros' => ['operacion' => 'operacion_empaque_id', 'pedido' => 'pedido_bma_id'],
        'operaciones_empaque' => ['operacion' => 'id'],
        'pedido_bma_caratulas' => ['pedido' => 'pedido_bma_id'],
        'pedido_bma_anexos_envio' => ['pedido' => 'pedido_bma_id'],
    ];

    public function up(): void
    {
        Schema::create('comisiones_empaque_cambios_fuente', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 16);
            $table->unsignedBigInteger('fuente_id');
            $table->index(['tipo', 'fuente_id']);
        });

        Schema::table('comisiones_empaque_sincronizaciones', function (Blueprint $table) {
            $table->boolean('automatica')->default(false)->index();
        });

        Schema::table('comisiones_empaque_unidades', function (Blueprint $table) {
            $table->index(['origen_tipo', 'origen_id', 'corte_id'], 'ce_unidades_fuente_corte_idx');
        });

        foreach (self::FUENTES as $tabla => $fuentes) {
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $evento) {
                $sentencias = [];
                foreach ($fuentes as $tipo => $columna) {
                    if ($evento !== 'DELETE') {
                        $sentencias[] = "INSERT INTO comisiones_empaque_cambios_fuente (tipo, fuente_id) SELECT '$tipo', NEW.$columna WHERE NEW.$columna IS NOT NULL";
                    }
                    if ($evento !== 'INSERT') {
                        $cambio = $evento === 'UPDATE' ? " AND (NEW.$columna IS NULL OR OLD.$columna <> NEW.$columna)" : '';
                        $sentencias[] = "INSERT INTO comisiones_empaque_cambios_fuente (tipo, fuente_id) SELECT '$tipo', OLD.$columna WHERE OLD.$columna IS NOT NULL$cambio";
                    }
                }
                $nombre = $this->nombre($tabla, $evento);
                $cuerpo = implode('; ', $sentencias);
                DB::unprepared("CREATE TRIGGER $nombre AFTER $evento ON $tabla FOR EACH ROW BEGIN $cuerpo; END");
            }
        }

        // MySQL no ejecuta triggers de las filas que elimina por cascada.
        DB::unprepared("CREATE TRIGGER ce_fuente_antes_borrar_pedido BEFORE DELETE ON pedidos_bma FOR EACH ROW BEGIN
            INSERT INTO comisiones_empaque_cambios_fuente (tipo, fuente_id)
            SELECT DISTINCT 'caja', pedido_bma_caja_id FROM pedido_bma_caja_remisiones WHERE pedido_bma_id = OLD.id AND activa = 1; END");
        DB::unprepared("CREATE TRIGGER ce_fuente_antes_borrar_operacion BEFORE DELETE ON operaciones_empaque FOR EACH ROW BEGIN
            INSERT INTO comisiones_empaque_cambios_fuente (tipo, fuente_id)
            SELECT DISTINCT 'pedido', pedido_bma_id FROM operacion_empaque_miembros WHERE operacion_empaque_id = OLD.id; END");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ce_fuente_antes_borrar_pedido');
        DB::unprepared('DROP TRIGGER IF EXISTS ce_fuente_antes_borrar_operacion');
        foreach (array_keys(self::FUENTES) as $tabla) {
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $evento) {
                DB::unprepared('DROP TRIGGER IF EXISTS '.$this->nombre($tabla, $evento));
            }
        }
        Schema::dropIfExists('comisiones_empaque_cambios_fuente');
        Schema::table('comisiones_empaque_unidades', fn (Blueprint $table) => $table->dropIndex('ce_unidades_fuente_corte_idx'));
        Schema::table('comisiones_empaque_sincronizaciones', fn (Blueprint $table) => $table->dropColumn('automatica'));
    }

    private function nombre(string $tabla, string $evento): string
    {
        return 'ce_fuente_'.strtolower($evento).'_'.$tabla;
    }
};
