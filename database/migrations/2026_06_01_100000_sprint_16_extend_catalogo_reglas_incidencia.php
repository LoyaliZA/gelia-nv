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
        Schema::table('catalogo_reglas_incidencia', function (Blueprint $table) {
            $table->enum('categoria', ['falta', 'retardo', 'operativa'])->default('operativa')->after('nombre');
            $table->decimal('factor_penalizacion_puntualidad', 8, 2)->default(0)->after('catalogo_bono_id');
            $table->decimal('factor_penalizacion_productividad', 8, 2)->default(0)->after('factor_penalizacion_puntualidad');
            $table->boolean('aplica_deduccion_salario_base')->default(false)->after('factor_penalizacion_productividad');
            $table->boolean('recompensa_auditor_activa')->default(false)->after('aplica_deduccion_salario_base');
            $table->decimal('monto_recompensa_auditor', 12, 2)->default(0)->after('recompensa_auditor_activa');
            $table->unsignedBigInteger('catalogo_tipo_falta_legacy_id')->nullable()->after('monto_recompensa_auditor');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE catalogo_reglas_incidencia MODIFY tipo_comportamiento ENUM(
                'cobro_fijo',
                'cobro_costo_producto',
                'cobro_precio_venta_producto',
                'cancelacion_bono_especifico',
                'deduccion_nomina'
            ) NOT NULL DEFAULT 'cobro_fijo'");
        }

        $this->migrarTiposFaltas();
    }

    private function migrarTiposFaltas(): void
    {
        if (!Schema::hasTable('catalogo_tipos_faltas')) {
            return;
        }

        $mapCategoria = [
            'Falta Injustificada' => 'falta',
            'Falta Justificada' => 'falta',
            'Retardo Menor (1 a 10 min)' => 'retardo',
            'Retardo Mayor (más de 10 min)' => 'retardo',
            'Permiso con Goce de Sueldo' => 'falta',
            'Permiso sin Goce de Sueldo' => 'falta',
        ];

        $tipos = DB::table('catalogo_tipos_faltas')->get();
        $now = now();
        if (DB::getDriverName() !== 'sqlite') {
            $folioNum = (int) DB::table('catalogo_reglas_incidencia')->max(DB::raw("CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)"));
        } else {
            $maxFolio = DB::table('catalogo_reglas_incidencia')->max('folio');
            $folioNum = 0;
            if ($maxFolio) {
                $parts = explode('-', $maxFolio);
                $folioNum = (int) end($parts);
            }
        }

        foreach ($tipos as $tipo) {
            $existente = DB::table('catalogo_reglas_incidencia')
                ->where('catalogo_tipo_falta_legacy_id', $tipo->id)
                ->exists();

            if ($existente) {
                continue;
            }

            $folioNum++;
            DB::table('catalogo_reglas_incidencia')->insert([
                'uuid' => (string) Str::uuid(),
                'folio' => 'REG-' . str_pad((string) $folioNum, 6, '0', STR_PAD_LEFT),
                'nombre' => $tipo->nombre,
                'categoria' => $mapCategoria[$tipo->nombre] ?? 'falta',
                'tipo_comportamiento' => 'deduccion_nomina',
                'monto_fijo' => null,
                'catalogo_bono_id' => null,
                'factor_penalizacion_puntualidad' => $tipo->factor_penalizacion_puntualidad,
                'factor_penalizacion_productividad' => $tipo->factor_penalizacion_productividad,
                'aplica_deduccion_salario_base' => (bool) $tipo->aplica_deduccion_salario_base,
                'recompensa_auditor_activa' => false,
                'monto_recompensa_auditor' => 0,
                'catalogo_tipo_falta_legacy_id' => $tipo->id,
                'activo' => (bool) $tipo->activo,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('catalogo_reglas_incidencia')
            ->whereNotNull('catalogo_tipo_falta_legacy_id')
            ->delete();

        Schema::table('catalogo_reglas_incidencia', function (Blueprint $table) {
            $table->dropColumn([
                'categoria',
                'factor_penalizacion_puntualidad',
                'factor_penalizacion_productividad',
                'aplica_deduccion_salario_base',
                'recompensa_auditor_activa',
                'monto_recompensa_auditor',
                'catalogo_tipo_falta_legacy_id',
            ]);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE catalogo_reglas_incidencia MODIFY tipo_comportamiento ENUM(
                'cobro_fijo',
                'cobro_costo_producto',
                'cobro_precio_venta_producto',
                'cancelacion_bono_especifico'
            ) NOT NULL");
        }
    }
};
