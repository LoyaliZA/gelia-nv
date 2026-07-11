<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->timestamp('empacado_at')->nullable()->after('pago_validado_por_id');
            $table->foreignId('empacado_por_id')->nullable()->after('empacado_at')
                ->constrained('users')->nullOnDelete();
            $table->text('detalle_incidencia_empaque')->nullable()->after('empacado_por_id');
            $table->timestamp('incidencia_empaque_at')->nullable()->after('detalle_incidencia_empaque');
            $table->foreignId('incidencia_empaque_por_id')->nullable()->after('incidencia_empaque_at')
                ->constrained('users')->nullOnDelete();
        });

        PermisoCatalogoMigracion::registrar('control_pedidos.cedis');
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('incidencia_empaque_por_id');
            $table->dropColumn('incidencia_empaque_at');
            $table->dropColumn('detalle_incidencia_empaque');
            $table->dropConstrainedForeignId('empacado_por_id');
            $table->dropColumn('empacado_at');
        });

        \Spatie\Permission\Models\Permission::where('name', 'control_pedidos.cedis')->delete();
    }
};
