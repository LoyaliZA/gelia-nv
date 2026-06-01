<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_incidencias') && !Schema::hasTable('rh_deducciones')) {
            Schema::rename('rh_incidencias', 'rh_deducciones');
        }

        if (!Schema::hasTable('rh_deducciones')) {
            return;
        }

        Schema::table('rh_deducciones', function (Blueprint $table) {
            if (!Schema::hasColumn('rh_deducciones', 'catalogo_regla_incidencia_id')) {
                $table->foreignId('catalogo_regla_incidencia_id')->nullable()->after('rh_colaborador_id')
                    ->constrained('catalogo_reglas_incidencia');
            }
            if (!Schema::hasColumn('rh_deducciones', 'producto_id')) {
                $table->foreignId('producto_id')->nullable()->after('catalogo_regla_incidencia_id')
                    ->constrained('productos')->nullOnDelete();
            }
            if (!Schema::hasColumn('rh_deducciones', 'producto_sku_snapshot')) {
                $table->string('producto_sku_snapshot')->nullable()->after('producto_id');
            }
            if (!Schema::hasColumn('rh_deducciones', 'monto_deduccion_base')) {
                $after = Schema::hasColumn('rh_deducciones', 'catalogo_tipo_falta_id')
                    ? 'catalogo_tipo_falta_id'
                    : 'rh_colaborador_id';
                $table->decimal('monto_deduccion_base', 12, 2)->default(0)->after($after);
            }
            if (!Schema::hasColumn('rh_deducciones', 'factor_multiplicador')) {
                $table->decimal('factor_multiplicador', 8, 2)->default(1)->after('monto_deduccion_base');
            }
            if (!Schema::hasColumn('rh_deducciones', 'total_parcial')) {
                $table->decimal('total_parcial', 12, 2)->default(0)->after('factor_multiplicador');
            }
            if (!Schema::hasColumn('rh_deducciones', 'monto_total_final')) {
                $table->decimal('monto_total_final', 12, 2)->default(0)->after('total_parcial');
            }
            if (!Schema::hasColumn('rh_deducciones', 'origen_deduccion')) {
                $table->enum('origen_deduccion', ['nomina', 'comisiones'])->default('nomina')->after('total_deduccion');
            }
            if (!Schema::hasColumn('rh_deducciones', 'fecha_aplicacion_deduccion')) {
                $table->date('fecha_aplicacion_deduccion')->nullable()->after('fecha_deduccion_nomina');
            }
            if (!Schema::hasColumn('rh_deducciones', 'firma_gerente_path')) {
                $table->string('firma_gerente_path')->nullable()->after('registrado_por_id');
            }
            if (!Schema::hasColumn('rh_deducciones', 'firma_colaborador_path')) {
                $table->string('firma_colaborador_path')->nullable()->after('firma_gerente_path');
            }
            if (!Schema::hasColumn('rh_deducciones', 'departamento_snapshot')) {
                $table->string('departamento_snapshot')->nullable()->after('firma_colaborador_path');
            }
            if (!Schema::hasColumn('rh_deducciones', 'area_snapshot')) {
                $table->string('area_snapshot')->nullable()->after('departamento_snapshot');
            }
            if (!Schema::hasColumn('rh_deducciones', 'regla_nombre_snapshot')) {
                $table->string('regla_nombre_snapshot')->nullable()->after('area_snapshot');
            }
            if (!Schema::hasColumn('rh_deducciones', 'regla_comportamiento_snapshot')) {
                $table->string('regla_comportamiento_snapshot')->nullable()->after('regla_nombre_snapshot');
            }
        });

        if (Schema::hasColumn('rh_deducciones', 'catalogo_tipo_falta_id')) {
            $this->migrarIncidenciasARreglas();
            $this->eliminarColumnaTipoFalta();
        }

        $this->actualizarEstadoDeduccion();

        if (Schema::hasColumn('rh_deducciones', 'observaciones') && !Schema::hasColumn('rh_deducciones', 'descripcion_detallada')) {
            DB::statement('ALTER TABLE rh_deducciones CHANGE observaciones descripcion_detallada TEXT NULL');
        }

        $this->seedPermisos();
    }

    private function eliminarColumnaTipoFalta(): void
    {
        $this->eliminarForeignKeyPorColumna('rh_deducciones', 'catalogo_tipo_falta_id');

        Schema::table('rh_deducciones', function (Blueprint $table) {
            if (Schema::hasColumn('rh_deducciones', 'catalogo_tipo_falta_id')) {
                $table->dropColumn('catalogo_tipo_falta_id');
            }
            if (Schema::hasColumn('rh_deducciones', 'tipo_falta_nombre_snapshot')) {
                $table->dropColumn('tipo_falta_nombre_snapshot');
            }
        });
    }

    private function eliminarForeignKeyPorColumna(string $tabla, string $columna): void
    {
        $constraint = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS nombre
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$tabla, $columna],
        );

        if (!$constraint?->nombre) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
            str_replace('`', '``', $tabla),
            str_replace('`', '``', $constraint->nombre),
        ));
    }

    private function actualizarEstadoDeduccion(): void
    {
        $tipo = strtolower($this->tipoColumnaEstadoDeduccion() ?? '');

        if ($tipo === '') {
            return;
        }

        $enumTieneLegacy = str_contains($tipo, "'pendiente'") || str_contains($tipo, "'programado'");
        $enumFinal = str_contains($tipo, 'pendiente_nomina')
            && str_contains($tipo, 'pendiente_comision')
            && !str_contains($tipo, "'pendiente'");

        if ($enumTieneLegacy && !$enumFinal) {
            DB::statement("ALTER TABLE rh_deducciones MODIFY estado_deduccion ENUM('pendiente', 'programado', 'aplicado', 'pendiente_nomina', 'pendiente_comision') NOT NULL DEFAULT 'pendiente'");
        }

        DB::table('rh_deducciones')->whereIn('estado_deduccion', ['pendiente', 'programado'])->update(['estado_deduccion' => 'pendiente_nomina']);

        if (Schema::hasColumn('rh_deducciones', 'monto_total_final')) {
            DB::table('rh_deducciones')
                ->where(function ($q) {
                    $q->whereNull('monto_total_final')->orWhere('monto_total_final', 0);
                })
                ->update([
                    'monto_total_final' => DB::raw('total_deduccion'),
                    'monto_deduccion_base' => DB::raw('total_deduccion'),
                ]);
        }

        $tipo = strtolower($this->tipoColumnaEstadoDeduccion() ?? '');
        if (str_contains($tipo, "'pendiente'") || str_contains($tipo, "'programado'")) {
            DB::statement("ALTER TABLE rh_deducciones MODIFY estado_deduccion ENUM('pendiente_nomina', 'pendiente_comision', 'aplicado') NOT NULL DEFAULT 'pendiente_nomina'");
        }
    }

    private function tipoColumnaEstadoDeduccion(): ?string
    {
        $columna = DB::selectOne(
            "SELECT COLUMN_TYPE AS tipo
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'rh_deducciones'
               AND COLUMN_NAME = 'estado_deduccion'
             LIMIT 1",
        );

        return $columna->tipo ?? null;
    }

    private function migrarIncidenciasARreglas(): void
    {
        if (!Schema::hasTable('catalogo_reglas_incidencia')) {
            return;
        }

        $map = DB::table('catalogo_reglas_incidencia')
            ->whereNotNull('catalogo_tipo_falta_legacy_id')
            ->pluck('id', 'catalogo_tipo_falta_legacy_id');

        foreach (DB::table('rh_deducciones')->whereNull('catalogo_regla_incidencia_id')->get() as $row) {
            if (!isset($row->catalogo_tipo_falta_id)) {
                continue;
            }

            $reglaId = $map[$row->catalogo_tipo_falta_id] ?? null;
            if (!$reglaId) {
                continue;
            }

            DB::table('rh_deducciones')->where('id', $row->id)->update([
                'catalogo_regla_incidencia_id' => $reglaId,
                'regla_nombre_snapshot' => $row->tipo_falta_nombre_snapshot ?? null,
                'regla_comportamiento_snapshot' => 'deduccion_nomina',
                'monto_deduccion_base' => $row->total_deduccion,
                'factor_multiplicador' => 1,
                'total_parcial' => $row->total_deduccion,
                'monto_total_final' => $row->total_deduccion,
                'origen_deduccion' => 'nomina',
            ]);
        }
    }

    private function seedPermisos(): void
    {
        $map = [
            'rh.incidencias.ver' => 'rh.deducciones.ver',
            'rh.incidencias.crear' => 'rh.deducciones.crear',
            'rh.incidencias.editar' => 'rh.deducciones.editar',
            'rh.incidencias.aplicar' => 'rh.deducciones.aplicar',
        ];

        foreach ($map as $new) {
            Permission::findOrCreate($new, 'web');
        }

        Permission::findOrCreate('rh.periodo_pago.ver', 'web');
        Permission::findOrCreate('rh.comisiones_auditor.ver', 'web');
    }

    public function down(): void
    {
        if (!Schema::hasTable('rh_deducciones')) {
            return;
        }

        if (Schema::hasColumn('rh_deducciones', 'descripcion_detallada') && !Schema::hasColumn('rh_deducciones', 'observaciones')) {
            DB::statement('ALTER TABLE rh_deducciones CHANGE descripcion_detallada observaciones TEXT NULL');
        }

        Schema::table('rh_deducciones', function (Blueprint $table) {
            if (!Schema::hasColumn('rh_deducciones', 'catalogo_tipo_falta_id')) {
                $table->foreignId('catalogo_tipo_falta_id')->nullable()->constrained('catalogo_tipos_faltas');
            }
            if (!Schema::hasColumn('rh_deducciones', 'tipo_falta_nombre_snapshot')) {
                $table->string('tipo_falta_nombre_snapshot')->nullable();
            }
        });

        if (Schema::hasColumn('rh_deducciones', 'catalogo_regla_incidencia_id')) {
            $this->eliminarForeignKeyPorColumna('rh_deducciones', 'catalogo_regla_incidencia_id');
        }
        if (Schema::hasColumn('rh_deducciones', 'producto_id')) {
            $this->eliminarForeignKeyPorColumna('rh_deducciones', 'producto_id');
        }

        Schema::table('rh_deducciones', function (Blueprint $table) {
            if (Schema::hasColumn('rh_deducciones', 'catalogo_regla_incidencia_id')) {
                $table->dropColumn('catalogo_regla_incidencia_id');
            }
            if (Schema::hasColumn('rh_deducciones', 'producto_id')) {
                $table->dropColumn('producto_id');
            }

            $columnas = [
                'producto_sku_snapshot',
                'monto_deduccion_base',
                'factor_multiplicador',
                'total_parcial',
                'monto_total_final',
                'origen_deduccion',
                'fecha_aplicacion_deduccion',
                'firma_gerente_path',
                'firma_colaborador_path',
                'departamento_snapshot',
                'area_snapshot',
                'regla_nombre_snapshot',
                'regla_comportamiento_snapshot',
            ];

            foreach ($columnas as $columna) {
                if (Schema::hasColumn('rh_deducciones', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });

        if (Schema::hasTable('rh_deducciones') && !Schema::hasTable('rh_incidencias')) {
            Schema::rename('rh_deducciones', 'rh_incidencias');
        }
    }
};
