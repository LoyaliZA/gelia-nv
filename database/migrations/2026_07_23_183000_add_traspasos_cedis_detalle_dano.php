<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private const PERMISOS = [
        'traspasos.cedis',
    ];

    public function up(): void
    {
        PermisoCatalogoMigracion::registrar(self::PERMISOS);

        Schema::table('solicitudes_traspasos', function (Blueprint $table) {
            $table->text('detalle_dano_motivo')->nullable()->after('respondida_at');
            $table->json('detalle_dano_paths')->nullable()->after('detalle_dano_motivo');
            $table->foreignId('detalle_dano_por_id')->nullable()->after('detalle_dano_paths')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('detalle_dano_at')->nullable()->after('detalle_dano_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_traspasos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('detalle_dano_por_id');
            $table->dropColumn([
                'detalle_dano_motivo',
                'detalle_dano_paths',
                'detalle_dano_at',
            ]);
        });

        Permission::whereIn('name', self::PERMISOS)->delete();
    }
};
