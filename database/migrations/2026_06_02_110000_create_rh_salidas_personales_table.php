<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_configuraciones')) {
            Schema::table('rh_configuraciones', function (Blueprint $table) {
                if (!Schema::hasColumn('rh_configuraciones', 'sal_folio_prefijo')) {
                    $table->string('sal_folio_prefijo', 20)->default('SAL')->after('pre_folio_padding');
                }
                if (!Schema::hasColumn('rh_configuraciones', 'sal_folio_padding')) {
                    $table->unsignedTinyInteger('sal_folio_padding')->default(6)->after('sal_folio_prefijo');
                }
            });
        }

        if (!Schema::hasTable('rh_salidas_personales')) {
            Schema::create('rh_salidas_personales', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('folio')->unique();
                $table->foreignId('rh_colaborador_id')->constrained('rh_colaboradores')->cascadeOnDelete();
                $table->date('fecha_evento');
                $table->string('motivo');
                $table->time('hora_salida');
                $table->string('evidencia_foto_salida');
                $table->time('hora_regreso')->nullable();
                $table->string('evidencia_foto_regreso')->nullable();
                $table->unsignedInteger('minutos_ausente')->default(0);
                $table->decimal('salario_por_minuto_snapshot', 12, 8)->default(0);
                $table->unsignedInteger('monto_a_deducir')->default(0);
                $table->date('fecha_deduccion_nomina')->nullable();
                $table->foreignId('registrado_por_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['fecha_evento', 'rh_colaborador_id'], 'rh_sal_fecha_colab_idx');
                $table->index('fecha_deduccion_nomina', 'rh_sal_deduc_nom_idx');
            });
        }

        foreach ([
            'rh.salidas_personales.ver',
            'rh.salidas_personales.crear',
            'rh.salidas_personales.editar',
            'rh.salidas_personales.eliminar',
            'rh.salidas_personales.sellar'
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_salidas_personales');

        if (Schema::hasTable('rh_configuraciones')) {
            Schema::table('rh_configuraciones', function (Blueprint $table) {
                if (Schema::hasColumn('rh_configuraciones', 'sal_folio_prefijo')) {
                    $table->dropColumn('sal_folio_prefijo');
                }
                if (Schema::hasColumn('rh_configuraciones', 'sal_folio_padding')) {
                    $table->dropColumn('sal_folio_padding');
                }
            });
        }
    }
};
