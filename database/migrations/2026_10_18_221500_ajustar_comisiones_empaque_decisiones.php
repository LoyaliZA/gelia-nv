<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private const PERMISOS = [
        'gestion_interna.comisiones_empaque.capturar',
        'gestion_interna.comisiones_empaque.ver_todas',
    ];

    public function up(): void
    {
        Schema::table('comisiones_empaque_unidades', function (Blueprint $table) {
            $table->string('ancla_decision', 40)->nullable()->after('huella');
            $table->string('ancla_huella', 64)->nullable()->after('ancla_decision');
            $table->text('ancla_motivo')->nullable()->after('ancla_huella');
            $table->foreignId('ancla_por_id')->nullable()->after('ancla_motivo')->constrained('users')->nullOnDelete();
            $table->timestamp('ancla_at')->nullable()->after('ancla_por_id');
            $table->foreignId('capturado_por_id')->nullable()->after('empacador_user_id')->constrained('users')->nullOnDelete();
            $table->string('clave_idempotencia', 80)->nullable()->unique('ce_unidades_idem_uq');
            $table->boolean('posible_duplicado')->default(false);
            $table->json('duplicados_relacionados')->nullable();
            $table->json('duplicados_reconocidos')->nullable();
        });

        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        Schema::table('comisiones_empaque_unidades', function (Blueprint $table) {
            $table->dropUnique('ce_unidades_idem_uq');
            $table->dropConstrainedForeignId('ancla_por_id');
            $table->dropConstrainedForeignId('capturado_por_id');
            $table->dropColumn([
                'ancla_decision', 'ancla_huella', 'ancla_motivo', 'ancla_at',
                'clave_idempotencia', 'posible_duplicado', 'duplicados_relacionados', 'duplicados_reconocidos',
            ]);
        });

        Permission::query()->whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();
    }
};
