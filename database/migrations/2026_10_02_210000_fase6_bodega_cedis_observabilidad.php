<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pedido_bma_tarea_productos')
            && ! Schema::hasColumn('pedido_bma_tarea_productos', 'pedido_bma_origen_id')) {
            Schema::table('pedido_bma_tarea_productos', function (Blueprint $table) {
                $table->unsignedBigInteger('pedido_bma_origen_id')->nullable()->after('pedido_bma_tarea_preparacion_id');
                $table->foreign('pedido_bma_origen_id', 'pb_tp_origen_fk')
                    ->references('id')->on('pedidos_bma')->nullOnDelete();
            });
        }

        if (Schema::hasTable('solicitud_traspaso_productos')
            && ! Schema::hasColumn('solicitud_traspaso_productos', 'pedido_bma_origen_id')) {
            Schema::table('solicitud_traspaso_productos', function (Blueprint $table) {
                $table->unsignedBigInteger('pedido_bma_origen_id')->nullable()->after('solicitud_traspaso_id');
                $table->foreign('pedido_bma_origen_id', 'stp_origen_fk')
                    ->references('id')->on('pedidos_bma')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('pedido_bma_traspaso_discrepancias')) {
            Schema::create('pedido_bma_traspaso_discrepancias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('solicitud_traspaso_id')
                    ->constrained('solicitudes_traspasos', indexName: 'pb_td_sol_trasp_fk')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('solicitud_traspaso_producto_id')->nullable();
                $table->unsignedBigInteger('pedido_bma_origen_id')->nullable();
                $table->string('sku', 64);
                $table->unsignedInteger('piezas_tienda')->default(0);
                $table->unsignedInteger('piezas_cedis')->default(0);
                $table->string('momento', 16)->default('CEDIS');
                $table->foreignId('registrado_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_td_reg_por_fk')
                    ->nullOnDelete();
                $table->timestamp('registrado_at')->useCurrent();
                $table->json('datos')->nullable();

                $table->index(['solicitud_traspaso_id', 'sku'], 'pb_td_sol_sku_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_traspaso_discrepancias');

        if (Schema::hasColumn('solicitud_traspaso_productos', 'pedido_bma_origen_id')) {
            Schema::table('solicitud_traspaso_productos', function (Blueprint $table) {
                $table->dropForeign('stp_origen_fk');
                $table->dropColumn('pedido_bma_origen_id');
            });
        }

        if (Schema::hasColumn('pedido_bma_tarea_productos', 'pedido_bma_origen_id')) {
            Schema::table('pedido_bma_tarea_productos', function (Blueprint $table) {
                $table->dropForeign('pb_tp_origen_fk');
                $table->dropColumn('pedido_bma_origen_id');
            });
        }
    }
};
