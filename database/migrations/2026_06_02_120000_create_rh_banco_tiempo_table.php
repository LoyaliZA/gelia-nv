<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar columnas de folio BDT a rh_configuraciones
        if (Schema::hasTable('rh_configuraciones')) {
            Schema::table('rh_configuraciones', function (Blueprint $table) {
                if (!Schema::hasColumn('rh_configuraciones', 'bdt_folio_prefijo')) {
                    $table->string('bdt_folio_prefijo', 20)->default('BDT')->after('sal_folio_padding');
                }
                if (!Schema::hasColumn('rh_configuraciones', 'bdt_folio_padding')) {
                    $table->unsignedTinyInteger('bdt_folio_padding')->default(6)->after('bdt_folio_prefijo');
                }
            });
        }

        if (!Schema::hasTable('rh_banco_tiempo')) {
            Schema::create('rh_banco_tiempo', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('folio')->unique();
                $table->foreignId('rh_colaborador_id')->constrained('rh_colaboradores')->cascadeOnDelete();
                $table->decimal('horas_pendientes', 5, 2);
                $table->text('origen_deuda');
                $table->string('estado', 20)->default('activa')->index();
                $table->date('fecha_acuerdo');
                $table->date('fecha_devolucion')->nullable();
                $table->foreignId('registrado_por_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['rh_colaborador_id', 'estado'], 'rh_bdt_colab_estado_idx');
                $table->index('fecha_acuerdo', 'rh_bdt_fecha_acuerdo_idx');
            });
        }

        foreach ([
            'rh.banco_tiempo.ver',
            'rh.banco_tiempo.crear',
            'rh.banco_tiempo.editar',
            'rh.banco_tiempo.saldar',
            'rh.banco_tiempo.eliminar',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_banco_tiempo');

        if (Schema::hasTable('rh_configuraciones')) {
            Schema::table('rh_configuraciones', function (Blueprint $table) {
                if (Schema::hasColumn('rh_configuraciones', 'bdt_folio_prefijo')) {
                    $table->dropColumn('bdt_folio_prefijo');
                }
                if (Schema::hasColumn('rh_configuraciones', 'bdt_folio_padding')) {
                    $table->dropColumn('bdt_folio_padding');
                }
            });
        }
    }
};
