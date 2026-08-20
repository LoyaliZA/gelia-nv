<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISOS = [
        'control_pedidos.eliminar_registro',
        'control_pedidos.eliminados',
    ];

    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->timestamp('eliminacion_registro_at')->nullable()->after('deleted_at');
            $table->foreignId('eliminacion_registro_por_id')->nullable()->after('eliminacion_registro_at')
                ->constrained('users')->nullOnDelete();
        });

        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eliminacion_registro_por_id');
            $table->dropColumn('eliminacion_registro_at');
        });

        \Spatie\Permission\Models\Permission::whereIn('name', self::PERMISOS)->delete();
    }
};
