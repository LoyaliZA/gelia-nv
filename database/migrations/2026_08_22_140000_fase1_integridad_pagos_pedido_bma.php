<?php

use App\Models\ConfiguracionSistema;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\ControlPedidos\PagosPedidoBmaConfig;
use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_pagos', function (Blueprint $table) {
            $table->foreignId('reemplaza_pago_id')
                ->nullable()
                ->after('pedido_bma_id')
                ->constrained('pedido_bma_pagos')
                ->nullOnDelete();
            $table->boolean('activo_para_cobertura')->default(true)->after('observaciones');
            $table->timestamp('rechazado_at')->nullable()->after('activo_para_cobertura');
            $table->foreignId('rechazado_por_id')->nullable()->after('rechazado_at')->constrained('users')->nullOnDelete();
            $table->text('motivo_rechazo')->nullable()->after('rechazado_por_id');
            $table->timestamp('sustituido_at')->nullable()->after('motivo_rechazo');

            $table->index(['pedido_bma_id', 'activo_para_cobertura'], 'pedido_bma_pagos_pedido_activo_idx');
            $table->index('rechazado_at');
        });

        Schema::create('catalogo_banco_departamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_banco_id')->constrained('catalogo_bancos')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['catalogo_banco_id', 'departamento_id'], 'banco_departamento_unique');
            $table->index(['departamento_id', 'activo']);
        });

        $leidos = (int) DB::table('pedido_bma_pagos')->count();
        $rechazados = 0;
        $activos = 0;

        DB::table('pedido_bma_pagos')
            ->orderBy('id')
            ->chunkById(200, function ($filas) use (&$rechazados, &$activos) {
                foreach ($filas as $fila) {
                    if ($fila->estado_revision === PedidoBmaPago::REVISION_RECHAZADO) {
                        DB::table('pedido_bma_pagos')->where('id', $fila->id)->update([
                            'activo_para_cobertura' => false,
                            'rechazado_at' => $fila->revisado_at ?? now(),
                            'motivo_rechazo' => $fila->observaciones,
                        ]);
                        $rechazados++;
                    } else {
                        DB::table('pedido_bma_pagos')->where('id', $fila->id)->update([
                            'activo_para_cobertura' => true,
                        ]);
                        $activos++;
                    }
                }
            });

        logger()->info('fase1_integridad_pagos backfill', [
            'leidos' => $leidos,
            'marcados_activos' => $activos,
            'marcados_rechazados' => $rechazados,
            'omitidos_sustitucion' => 0,
        ]);

        ConfiguracionSistema::updateOrCreate(
            ['clave' => PagosPedidoBmaConfig::CLAVE_TOLERANCIA],
            [
                'valor' => (string) PagosPedidoBmaConfig::DEFAULT_TOLERANCIA,
                'tipo' => 'decimal',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Tolerancia máxima (MXN) al validar cobertura de pagos del pedido',
            ]
        );

        ConfiguracionSistema::updateOrCreate(
            ['clave' => PagosPedidoBmaConfig::CLAVE_UI_SIMPLIFICADA],
            [
                'valor' => '0',
                'tipo' => 'boolean',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'UI simplificada del Auxiliar (tarjeta cobertura / rechazo / sin dirección)',
            ]
        );

        PermisoCatalogoMigracion::registrar([
            'control_pedidos.pagos.ver',
            'control_pedidos.pagos.adjuntar',
            'control_pedidos.pagos.sustituir',
            'control_pedidos.pagos.rechazar',
            'control_pedidos.pagos.validar',
            'control_pedidos.pagos.evidencia_historica',
            'control_pedidos.bancos_departamento.administrar',
        ]);
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'control_pedidos.pagos.ver',
            'control_pedidos.pagos.adjuntar',
            'control_pedidos.pagos.sustituir',
            'control_pedidos.pagos.rechazar',
            'control_pedidos.pagos.validar',
            'control_pedidos.pagos.evidencia_historica',
            'control_pedidos.bancos_departamento.administrar',
        ])->delete();

        ConfiguracionSistema::whereIn('clave', [
            PagosPedidoBmaConfig::CLAVE_TOLERANCIA,
            PagosPedidoBmaConfig::CLAVE_UI_SIMPLIFICADA,
        ])->delete();

        Schema::dropIfExists('catalogo_banco_departamento');

        Schema::table('pedido_bma_pagos', function (Blueprint $table) {
            $table->dropIndex('pedido_bma_pagos_pedido_activo_idx');
            $table->dropConstrainedForeignId('reemplaza_pago_id');
            $table->dropConstrainedForeignId('rechazado_por_id');
            $table->dropColumn([
                'activo_para_cobertura',
                'rechazado_at',
                'motivo_rechazo',
                'sustituido_at',
            ]);
        });
    }
};
