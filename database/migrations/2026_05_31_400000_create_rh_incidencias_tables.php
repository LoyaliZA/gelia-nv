<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_configuraciones', function (Blueprint $table) {
            $table->string('inc_folio_prefijo', 20)->default('INC')->after('he_minutos_minimos');
            $table->unsignedTinyInteger('inc_folio_padding')->default(6)->after('inc_folio_prefijo');
        });

        Schema::create('rh_incidencias', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio')->unique();
            $table->date('fecha_ocurrencia');
            $table->foreignId('rh_colaborador_id')->constrained('rh_colaboradores');
            $table->foreignId('catalogo_tipo_falta_id')->constrained('catalogo_tipos_faltas');
            $table->decimal('deduccion_salario_base', 12, 2)->default(0);
            $table->decimal('deduccion_bono_puntualidad', 12, 2)->default(0);
            $table->decimal('deduccion_bono_productividad', 12, 2)->default(0);
            $table->unsignedInteger('total_deduccion')->default(0);
            $table->text('observaciones')->nullable();
            $table->date('fecha_deduccion_nomina')->nullable();
            if (DB::getDriverName() === 'sqlite') {
                $table->string('estado_deduccion')->default('pendiente');
            } else {
                $table->enum('estado_deduccion', ['pendiente', 'programado'])->default('pendiente');
            }
            $table->decimal('salario_diario_snapshot', 12, 2)->default(0);
            $table->decimal('bono_puntualidad_diario_snapshot', 12, 2)->default(0);
            $table->decimal('bono_productividad_diario_snapshot', 12, 2)->default(0);
            $table->decimal('factor_puntualidad_snapshot', 8, 2)->default(0);
            $table->decimal('factor_productividad_snapshot', 8, 2)->default(0);
            $table->boolean('aplica_deduccion_salario_snapshot')->default(false);
            $table->string('tipo_falta_nombre_snapshot')->nullable();
            $table->foreignId('registrado_por_id')->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['fecha_ocurrencia', 'rh_colaborador_id']);
            $table->index(['estado_deduccion', 'fecha_deduccion_nomina']);
            $table->index('catalogo_tipo_falta_id');
        });

        $this->seedPermisos();
    }

    private function seedPermisos(): void
    {
        foreach (['rh.incidencias.ver', 'rh.incidencias.crear', 'rh.incidencias.editar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_incidencias');

        Schema::table('rh_configuraciones', function (Blueprint $table) {
            $table->dropColumn(['inc_folio_prefijo', 'inc_folio_padding']);
        });
    }
};
