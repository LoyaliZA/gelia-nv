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
            $table->string('he_folio_prefijo', 20)->default('HE')->after('decimales_salario_minuto');
            $table->unsignedTinyInteger('he_folio_padding')->default(6)->after('he_folio_prefijo');
            $table->decimal('he_multiplicador_pago', 4, 2)->default(2.00)->after('he_folio_padding');
            $table->unsignedSmallInteger('he_minutos_minimos')->default(30)->after('he_multiplicador_pago');
        });

        Schema::create('rh_horas_extra', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio')->unique();
            $table->foreignId('rh_colaborador_id')->constrained('rh_colaboradores');
            $table->date('fecha_turno');
            $table->time('hora_entrada');
            $table->time('hora_salida');
            $table->boolean('salida_dia_siguiente')->default(false);
            $table->decimal('total_horas_laboradas', 6, 2)->default(0);
            $table->decimal('horas_normales_snapshot', 4, 2)->default(8);
            $table->decimal('tiempo_extra_crudo', 6, 2)->default(0);
            $table->unsignedInteger('tiempo_extra_minutos')->default(0);
            $table->unsignedInteger('horas_extra_a_pagar')->default(0);
            $table->decimal('salario_por_hora_snapshot', 12, 4)->default(0);
            $table->decimal('multiplicador_snapshot', 4, 2)->default(2);
            $table->decimal('total_economico', 12, 2)->default(0);
            $table->text('motivo');
            $table->foreignId('supervisor_user_id')->constrained('users');
            $table->date('fecha_programada_pago')->nullable();
            $table->enum('estado_pago', ['pendiente', 'programado'])->default('pendiente');
            $table->string('area_snapshot')->nullable();
            $table->foreignId('registrado_por_id')->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['fecha_turno', 'rh_colaborador_id']);
            $table->index(['estado_pago', 'fecha_programada_pago']);
            $table->index('supervisor_user_id');
        });

        $this->seedPermisos();
    }

    private function seedPermisos(): void
    {
        foreach (['rh.horas_extra.ver', 'rh.horas_extra.crear', 'rh.horas_extra.editar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_horas_extra');

        Schema::table('rh_configuraciones', function (Blueprint $table) {
            $table->dropColumn([
                'he_folio_prefijo',
                'he_folio_padding',
                'he_multiplicador_pago',
                'he_minutos_minimos',
            ]);
        });
    }
};
