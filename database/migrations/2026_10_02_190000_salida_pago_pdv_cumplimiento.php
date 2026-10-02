<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = [
        'control_pedidos.salida.validar_pago',
        'control_pedidos.salida.autorizar',
        'control_pedidos.salida.confirmar_entrega',
    ];

    public function up(): void
    {
        Schema::table('pedido_bma_cumplimiento_fisico', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'condicion_cobro')) {
                $table->string('condicion_cobro', 20)->nullable()->after('version');
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'pago_confirmado_at')) {
                $table->timestamp('pago_confirmado_at')->nullable()->after('condicion_cobro');
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'pago_confirmado_por_id')) {
                $table->foreignId('pago_confirmado_por_id')->nullable()->after('pago_confirmado_at')
                    ->constrained('users', indexName: 'pb_cump_pago_por_fk')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'pedido_bma_pago_id')) {
                $table->foreignId('pedido_bma_pago_id')->nullable()->after('pago_confirmado_por_id')
                    ->constrained('pedido_bma_pagos', indexName: 'pb_cump_pago_fk')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'folio_operacion')) {
                $table->string('folio_operacion', 80)->nullable()->after('pedido_bma_pago_id');
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'salida_autorizada_at')) {
                $table->timestamp('salida_autorizada_at')->nullable()->after('folio_operacion');
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'salida_autorizada_por_id')) {
                $table->foreignId('salida_autorizada_por_id')->nullable()->after('salida_autorizada_at')
                    ->constrained('users', indexName: 'pb_cump_salida_por_fk')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'entregada_at')) {
                $table->timestamp('entregada_at')->nullable()->after('salida_autorizada_por_id');
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'entregada_por_id')) {
                $table->foreignId('entregada_por_id')->nullable()->after('entregada_at')
                    ->constrained('users', indexName: 'pb_cump_ent_por_fk')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'receptor_nombre')) {
                $table->string('receptor_nombre', 160)->nullable()->after('entregada_por_id');
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'resguardo_pdv_id')) {
                $table->foreignId('resguardo_pdv_id')->nullable()->after('receptor_nombre')
                    ->constrained('pdv_resguardos', indexName: 'pb_cump_resg_fk')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('pedido_bma_cumplimiento_fisico', 'entrega_pdv_id')) {
                $table->foreignId('entrega_pdv_id')->nullable()->after('resguardo_pdv_id')
                    ->constrained('pdv_resguardo_entregas', indexName: 'pb_cump_ent_pdv_fk')
                    ->nullOnDelete();
            }
        });

        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        Schema::table('pedido_bma_cumplimiento_fisico', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entrega_pdv_id');
            $table->dropConstrainedForeignId('resguardo_pdv_id');
            $table->dropConstrainedForeignId('entregada_por_id');
            $table->dropColumn(['entregada_at', 'receptor_nombre']);
            $table->dropConstrainedForeignId('salida_autorizada_por_id');
            $table->dropColumn('salida_autorizada_at');
            $table->dropConstrainedForeignId('pedido_bma_pago_id');
            $table->dropConstrainedForeignId('pago_confirmado_por_id');
            $table->dropColumn(['condicion_cobro', 'pago_confirmado_at', 'folio_operacion']);
        });
    }
};
