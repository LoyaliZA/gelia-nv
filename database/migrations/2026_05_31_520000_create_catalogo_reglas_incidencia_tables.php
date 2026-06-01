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
        if (!Schema::hasTable('catalogo_reglas_incidencia')) {
            Schema::create('catalogo_reglas_incidencia', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('folio')->unique();
                $table->string('nombre');
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('tipo_comportamiento');
                } else {
                    $table->enum('tipo_comportamiento', [
                        'cobro_fijo',
                        'cobro_costo_producto',
                        'cobro_precio_venta_producto',
                        'cancelacion_bono_especifico',
                    ]);
                }
                $table->decimal('monto_fijo', 12, 2)->nullable();
                $table->foreignId('catalogo_bono_id')->nullable()->constrained('catalogo_bonos')->nullOnDelete();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('catalogo_regla_incidencia_departamento_aplicable')) {
            Schema::create('catalogo_regla_incidencia_departamento_aplicable', function (Blueprint $table) {
                $table->unsignedBigInteger('catalogo_regla_incidencia_id');
                $table->unsignedBigInteger('departamento_id');
                $table->primary(['catalogo_regla_incidencia_id', 'departamento_id'], 'regla_depto_aplicable_pk');
                $table->foreign('catalogo_regla_incidencia_id', 'cri_depto_aplic_regla_fk')
                    ->references('id')->on('catalogo_reglas_incidencia')->cascadeOnDelete();
                $table->foreign('departamento_id', 'cri_depto_aplic_depto_fk')
                    ->references('id')->on('departamentos')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('catalogo_regla_incidencia_area_aplicable')) {
            Schema::create('catalogo_regla_incidencia_area_aplicable', function (Blueprint $table) {
                $table->unsignedBigInteger('catalogo_regla_incidencia_id');
                $table->unsignedBigInteger('area_id');
                $table->primary(['catalogo_regla_incidencia_id', 'area_id'], 'regla_area_aplicable_pk');
                $table->foreign('catalogo_regla_incidencia_id', 'cri_area_aplic_regla_fk')
                    ->references('id')->on('catalogo_reglas_incidencia')->cascadeOnDelete();
                $table->foreign('area_id', 'cri_area_aplic_area_fk')
                    ->references('id')->on('areas')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('catalogo_regla_incidencia_departamento_visibilidad')) {
            Schema::create('catalogo_regla_incidencia_departamento_visibilidad', function (Blueprint $table) {
                $table->unsignedBigInteger('catalogo_regla_incidencia_id');
                $table->unsignedBigInteger('departamento_id');
                $table->primary(['catalogo_regla_incidencia_id', 'departamento_id'], 'regla_depto_vis_pk');
                $table->foreign('catalogo_regla_incidencia_id', 'cri_depto_vis_regla_fk')
                    ->references('id')->on('catalogo_reglas_incidencia')->cascadeOnDelete();
                $table->foreign('departamento_id', 'cri_depto_vis_depto_fk')
                    ->references('id')->on('departamentos')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('catalogo_regla_incidencia_area_visibilidad')) {
            Schema::create('catalogo_regla_incidencia_area_visibilidad', function (Blueprint $table) {
                $table->unsignedBigInteger('catalogo_regla_incidencia_id');
                $table->unsignedBigInteger('area_id');
                $table->primary(['catalogo_regla_incidencia_id', 'area_id'], 'regla_area_vis_pk');
                $table->foreign('catalogo_regla_incidencia_id', 'cri_area_vis_regla_fk')
                    ->references('id')->on('catalogo_reglas_incidencia')->cascadeOnDelete();
                $table->foreign('area_id', 'cri_area_vis_area_fk')
                    ->references('id')->on('areas')->cascadeOnDelete();
            });
        }

        Permission::findOrCreate('rh.catalogos.incidencias_generales', 'web');

        $this->seedReglasDemo();
    }

    private function seedReglasDemo(): void
    {
        if (DB::table('catalogo_reglas_incidencia')->where('folio', 'REG-000001')->exists()) {
            return;
        }

        $now = now();
        $bonoCajaId = DB::table('catalogo_bonos')->where('codigo', 'bono_caja')->value('id');

        $reglas = [
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'folio' => 'REG-000001',
                'nombre' => 'Nota Faltante',
                'tipo_comportamiento' => 'cobro_fijo',
                'monto_fijo' => 100.00,
                'catalogo_bono_id' => null,
            ],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'folio' => 'REG-000002',
                'nombre' => 'Perfume Roto',
                'tipo_comportamiento' => 'cobro_costo_producto',
                'monto_fijo' => null,
                'catalogo_bono_id' => null,
            ],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'folio' => 'REG-000003',
                'nombre' => 'Insubordinación',
                'tipo_comportamiento' => 'cancelacion_bono_especifico',
                'monto_fijo' => null,
                'catalogo_bono_id' => $bonoCajaId,
            ],
        ];

        foreach ($reglas as $regla) {
            DB::table('catalogo_reglas_incidencia')->insert([
                ...$regla,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_regla_incidencia_area_visibilidad');
        Schema::dropIfExists('catalogo_regla_incidencia_departamento_visibilidad');
        Schema::dropIfExists('catalogo_regla_incidencia_area_aplicable');
        Schema::dropIfExists('catalogo_regla_incidencia_departamento_aplicable');
        Schema::dropIfExists('catalogo_reglas_incidencia');
    }
};
