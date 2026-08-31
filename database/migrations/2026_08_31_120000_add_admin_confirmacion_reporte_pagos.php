<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_cierre_pago_items', function (Blueprint $table) {
            $table->string('admin_estado', 20)->default('pendiente')->after('motivo_rechazo_snapshot');
            $table->unsignedBigInteger('admin_confirmado_por_id')->nullable()->after('admin_estado');
            $table->timestamp('admin_confirmado_at')->nullable()->after('admin_confirmado_por_id');
            $table->text('admin_error_comentario')->nullable()->after('admin_confirmado_at');
            $table->string('admin_error_evidencia_ruta')->nullable()->after('admin_error_comentario');
            $table->string('admin_error_evidencia_nombre')->nullable()->after('admin_error_evidencia_ruta');
            $table->unsignedBigInteger('admin_error_reportado_por_id')->nullable()->after('admin_error_evidencia_nombre');
            $table->timestamp('admin_error_reportado_at')->nullable()->after('admin_error_reportado_por_id');

            $table->index('admin_estado', 'pbcpi_admin_estado_idx');
            $table->foreign('admin_confirmado_por_id', 'pbcpi_admin_conf_por_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('admin_error_reportado_por_id', 'pbcpi_admin_err_por_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('pedido_bma_cierres_pago', function (Blueprint $table) {
            $table->text('admin_pedido_error_comentario')->nullable()->after('metadata_snapshot');
            $table->string('admin_pedido_error_evidencia_ruta')->nullable()->after('admin_pedido_error_comentario');
            $table->string('admin_pedido_error_evidencia_nombre')->nullable()->after('admin_pedido_error_evidencia_ruta');
            $table->unsignedBigInteger('admin_pedido_error_reportado_por_id')->nullable()->after('admin_pedido_error_evidencia_nombre');
            $table->timestamp('admin_pedido_error_reportado_at')->nullable()->after('admin_pedido_error_reportado_por_id');

            $table->foreign('admin_pedido_error_reportado_por_id', 'pbcp_admin_err_por_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        PermisoCatalogoMigracion::registrar([
            'reportes.pagos_pedidos.confirmar_admin',
            'reportes.pagos_pedidos.reportar_error_admin',
        ]);
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'reportes.pagos_pedidos.confirmar_admin',
            'reportes.pagos_pedidos.reportar_error_admin',
        ])->delete();

        Schema::table('pedido_bma_cierres_pago', function (Blueprint $table) {
            $table->dropForeign('pbcp_admin_err_por_fk');
            $table->dropColumn([
                'admin_pedido_error_comentario',
                'admin_pedido_error_evidencia_ruta',
                'admin_pedido_error_evidencia_nombre',
                'admin_pedido_error_reportado_por_id',
                'admin_pedido_error_reportado_at',
            ]);
        });

        Schema::table('pedido_bma_cierre_pago_items', function (Blueprint $table) {
            $table->dropIndex('pbcpi_admin_estado_idx');
            $table->dropForeign('pbcpi_admin_err_por_fk');
            $table->dropForeign('pbcpi_admin_conf_por_fk');
            $table->dropColumn([
                'admin_estado',
                'admin_confirmado_por_id',
                'admin_confirmado_at',
                'admin_error_comentario',
                'admin_error_evidencia_ruta',
                'admin_error_evidencia_nombre',
                'admin_error_reportado_por_id',
                'admin_error_reportado_at',
            ]);
        });
    }
};
