<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_procesos', function (Blueprint $table) {
            $table->string('categoria_flujo', 20)->default('financiero')->after('descripcion');
        });

        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->string('numero_remision')->nullable()->after('observaciones_vendedor');
            $table->string('numero_pedido')->nullable()->after('numero_remision');
            $table->date('fecha_operacion')->nullable()->after('numero_pedido');
            $table->text('motivo_operacion')->nullable()->after('fecha_operacion');
            $table->foreignId('catalogo_banco_id')->nullable()->after('motivo_operacion')
                ->constrained('catalogo_bancos')->nullOnDelete();
            $table->boolean('solicitar_cotizacion')->default(false)->after('catalogo_banco_id');
            $table->timestamp('cancelacion_solicitada_at')->nullable()->after('rollback_confirmado_at');
            $table->text('motivo_cancelacion')->nullable()->after('cancelacion_solicitada_at');
        });

        DB::table('catalogo_estados_solicitud')->updateOrInsert(
            ['nombre' => 'Cancelada'],
            ['descripcion' => 'Solicitud anulada con reversión de cambios si aplica', 'activo' => true, 'updated_at' => now(), 'created_at' => now()]
        );

        $procesosOperativos = [
            ['nombre' => 'CANCELACIÓN DE REMISIÓN', 'categoria_flujo' => 'operativo'],
            ['nombre' => 'CANCELACIÓN DE PEDIDO', 'categoria_flujo' => 'operativo'],
            ['nombre' => 'SOLICITAR COTIZACIÓN SOBRE PEDIDO', 'categoria_flujo' => 'operativo'],
        ];

        foreach ($procesosOperativos as $proceso) {
            DB::table('catalogo_procesos')->updateOrInsert(
                ['nombre' => $proceso['nombre']],
                array_merge($proceso, ['activo' => true, 'updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    public function down(): void
    {
        DB::table('catalogo_procesos')->whereIn('nombre', [
            'CANCELACIÓN DE REMISIÓN',
            'CANCELACIÓN DE PEDIDO',
            'SOLICITAR COTIZACIÓN SOBRE PEDIDO',
        ])->delete();

        DB::table('catalogo_estados_solicitud')->where('nombre', 'Cancelada')->delete();

        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropForeign(['catalogo_banco_id']);
            $table->dropColumn([
                'numero_remision',
                'numero_pedido',
                'fecha_operacion',
                'motivo_operacion',
                'catalogo_banco_id',
                'solicitar_cotizacion',
                'cancelacion_solicitada_at',
                'motivo_cancelacion',
            ]);
        });

        Schema::table('catalogo_procesos', function (Blueprint $table) {
            $table->dropColumn('categoria_flujo');
        });
    }
};
