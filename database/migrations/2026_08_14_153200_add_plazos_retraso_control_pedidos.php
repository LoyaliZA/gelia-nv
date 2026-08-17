<?php

use App\Models\ConfiguracionSistema;
use App\Services\ControlPedidos\PlazosRetrasoPedidoBmaConfig;
use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->timestamp('retraso_empaque_alertado_at')->nullable()->after('empacado_por_id');
            $table->timestamp('retraso_recoleccion_alertado_at')->nullable()->after('retraso_empaque_alertado_at');
        });

        Schema::table('pedido_bma_historial_estados', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->foreignId('usuario_id')->nullable()->change();
            $table->foreign('usuario_id')->references('id')->on('users')->nullOnDelete();
        });

        PermisoCatalogoMigracion::registrar('control_pedidos.configurar_plazos');

        $defaults = (new PlazosRetrasoPedidoBmaConfig)->configuracionPorDefecto();

        ConfiguracionSistema::updateOrCreate(
            ['clave' => PlazosRetrasoPedidoBmaConfig::CLAVE],
            [
                'valor' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Plazos de retraso de empaque y recolección (Control Pedidos)',
            ]
        );
    }

    public function down(): void
    {
        ConfiguracionSistema::where('clave', PlazosRetrasoPedidoBmaConfig::CLAVE)->delete();

        Permission::where('name', 'control_pedidos.configurar_plazos')->delete();

        Schema::table('pedido_bma_historial_estados', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
        });

        // Restaurar NOT NULL solo si no hay filas con usuario_id null
        Schema::table('pedido_bma_historial_estados', function (Blueprint $table) {
            $table->foreignId('usuario_id')->nullable(false)->change();
            $table->foreign('usuario_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropColumn(['retraso_empaque_alertado_at', 'retraso_recoleccion_alertado_at']);
        });
    }
};
