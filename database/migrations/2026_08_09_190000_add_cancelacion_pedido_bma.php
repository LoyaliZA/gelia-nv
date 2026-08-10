<?php

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        PermisoCatalogoMigracion::registrar([
            'control_pedidos.cancelar',
        ]);

        CatalogoEstatusPedido::query()->firstOrCreate(
            ['codigo_interno' => 'CANCELADO'],
            [
                'nombre_visual' => 'Cancelado',
                'color_hex' => '#64748B',
                'fase_ciclo' => 'CANCELADO',
                'orden' => 99,
                'activo' => true,
            ]
        );

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->string('motivo_cancelacion', 64)->nullable()->after('tiene_observaciones_fisicas');
            $table->text('comentario_cancelacion')->nullable()->after('motivo_cancelacion');
            $table->string('resolucion_financiera_cancelacion', 64)->nullable()->after('comentario_cancelacion');
            $table->foreignId('cancelado_por_id')->nullable()->after('resolucion_financiera_cancelacion')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('cancelado_at')->nullable()->after('cancelado_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelado_por_id');
            $table->dropColumn([
                'motivo_cancelacion',
                'comentario_cancelacion',
                'resolucion_financiera_cancelacion',
                'cancelado_at',
            ]);
        });

        CatalogoEstatusPedido::where('codigo_interno', 'CANCELADO')->delete();
        \Spatie\Permission\Models\Permission::where('name', 'control_pedidos.cancelar')->delete();
    }
};
