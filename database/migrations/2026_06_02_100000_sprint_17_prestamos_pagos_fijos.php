<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rh_prestamos_pagos_fijos')) {
            Schema::create('rh_prestamos_pagos_fijos', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('folio')->unique();
                $table->foreignId('rh_colaborador_id')->constrained('rh_colaboradores')->cascadeOnDelete();
                $table->text('concepto');
                $table->decimal('monto_cuota', 12, 2);
                $table->unsignedInteger('num_pagos_total')->nullable();
                $table->unsignedInteger('pagos_realizados')->default(0);
                $table->enum('modalidad', ['recurrente', 'unica_vez'])->default('recurrente');
                $table->text('observaciones')->nullable();
                $table->date('fecha_ejecucion_programada')->nullable();
                $table->date('fecha_inicio');
                $table->enum('estado', ['activo', 'pausado', 'liquidado', 'cancelado'])->default('activo');
                $table->date('ultimo_periodo_generado')->nullable();
                $table->foreignId('registrado_por_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['rh_colaborador_id', 'estado'], 'rh_prest_colab_estado_idx');
                $table->index(['estado', 'modalidad'], 'rh_prest_estado_mod_idx');
            });
        }

        if (Schema::hasTable('rh_deducciones') && !Schema::hasColumn('rh_deducciones', 'rh_prestamo_pago_fijo_id')) {
            Schema::table('rh_deducciones', function (Blueprint $table) {
                $table->foreignId('rh_prestamo_pago_fijo_id')->nullable()->after('rh_colaborador_id')
                    ->constrained('rh_prestamos_pagos_fijos')->nullOnDelete();
                $table->unsignedInteger('numero_cuota')->nullable()->after('rh_prestamo_pago_fijo_id');
            });
        }

        if (Schema::hasTable('rh_configuraciones')) {
            Schema::table('rh_configuraciones', function (Blueprint $table) {
                if (!Schema::hasColumn('rh_configuraciones', 'pre_folio_prefijo')) {
                    $table->string('pre_folio_prefijo', 20)->default('PRE')->after('inc_folio_padding');
                }
                if (!Schema::hasColumn('rh_configuraciones', 'pre_folio_padding')) {
                    $table->unsignedTinyInteger('pre_folio_padding')->default(6)->after('pre_folio_prefijo');
                }
            });
        }

        foreach (['rh.prestamos.ver', 'rh.prestamos.crear', 'rh.prestamos.editar', 'rh.prestamos.detener', 'rh.prestamos.generar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rh_deducciones') && Schema::hasColumn('rh_deducciones', 'rh_prestamo_pago_fijo_id')) {
            Schema::table('rh_deducciones', function (Blueprint $table) {
                $table->dropConstrainedForeignId('rh_prestamo_pago_fijo_id');
                $table->dropColumn('numero_cuota');
            });
        }

        Schema::dropIfExists('rh_prestamos_pagos_fijos');

        if (Schema::hasTable('rh_configuraciones')) {
            Schema::table('rh_configuraciones', function (Blueprint $table) {
                if (Schema::hasColumn('rh_configuraciones', 'pre_folio_prefijo')) {
                    $table->dropColumn('pre_folio_prefijo');
                }
                if (Schema::hasColumn('rh_configuraciones', 'pre_folio_padding')) {
                    $table->dropColumn('pre_folio_padding');
                }
            });
        }
    }
};
