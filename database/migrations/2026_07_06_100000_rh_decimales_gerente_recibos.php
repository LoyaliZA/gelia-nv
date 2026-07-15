<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_salidas_personales') && Schema::hasColumn('rh_salidas_personales', 'monto_a_deducir')) {
            Schema::table('rh_salidas_personales', function (Blueprint $table) {
                $table->decimal('monto_a_deducir', 12, 2)->default(0)->change();
            });
        }

        if (Schema::hasTable('rh_deducciones') && Schema::hasColumn('rh_deducciones', 'total_deduccion')) {
            Schema::table('rh_deducciones', function (Blueprint $table) {
                $table->decimal('total_deduccion', 12, 2)->default(0)->change();
            });
        }

        if (!Schema::hasTable('gerente_rh_colaborador')) {
            Schema::create('gerente_rh_colaborador', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gerente_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('rh_colaborador_id')->constrained('rh_colaboradores')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['gerente_user_id', 'rh_colaborador_id'], 'gerente_rh_colab_unique');
            });
        }

        $permisos = [
            'rh.incidencias.gerente.ver',
            'rh.incidencias.gerente.crear',
            'rh.recibos.ver',
            'rh.recibos.generar',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $gerente = Role::where('name', 'Gerente')->where('guard_name', 'web')->first();
        if ($gerente) {
            $gerente->givePermissionTo([
                'rh.incidencias.gerente.ver',
                'rh.incidencias.gerente.crear',
                'rh.recibos.ver',
                'rh.recibos.generar',
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gerente_rh_colaborador');

        foreach ([
            'rh.incidencias.gerente.ver',
            'rh.incidencias.gerente.crear',
            'rh.recibos.ver',
            'rh.recibos.generar',
        ] as $permiso) {
            Permission::where('name', $permiso)->delete();
        }
    }
};
