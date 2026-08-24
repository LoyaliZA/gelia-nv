<?php

use App\Models\ConfiguracionSistema;
use App\Services\ControlPedidos\EnviosPedidoBmaConfig;
use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_cajas', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_bma_cajas', 'uuid_operativo')) {
                $table->uuid('uuid_operativo')->nullable()->after('pedido_bma_id');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'costo_envio')) {
                $table->decimal('costo_envio', 14, 2)->nullable()->after('numero_rastreo');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'costo_seguro')) {
                $table->decimal('costo_seguro', 14, 2)->nullable()->after('costo_envio');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'costo_adicional')) {
                $table->decimal('costo_adicional', 14, 2)->nullable()->after('costo_seguro');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'concepto_adicional')) {
                $table->string('concepto_adicional', 128)->nullable()->after('costo_adicional');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'moneda')) {
                $table->char('moneda', 3)->default('MXN')->after('concepto_adicional');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'estado_operativo')) {
                $table->string('estado_operativo', 20)->default('activa')->after('moneda');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'retirada_at')) {
                $table->timestamp('retirada_at')->nullable()->after('estado_operativo');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'retirada_por_id')) {
                $table->foreignId('retirada_por_id')->nullable()->after('retirada_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'motivo_retiro')) {
                $table->text('motivo_retiro')->nullable()->after('retirada_por_id');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'costos_actualizados_at')) {
                $table->timestamp('costos_actualizados_at')->nullable()->after('motivo_retiro');
            }
            if (! Schema::hasColumn('pedido_bma_cajas', 'costos_actualizados_por_id')) {
                $table->foreignId('costos_actualizados_por_id')->nullable()->after('costos_actualizados_at')
                    ->constrained('users')->nullOnDelete();
            }
        });

        try {
            Schema::table('pedido_bma_cajas', function (Blueprint $table) {
                $table->unique(['pedido_bma_id', 'uuid_operativo'], 'pedido_bma_cajas_pedido_uuid_unique');
                $table->index(['pedido_bma_id', 'estado_operativo'], 'pedido_bma_cajas_pedido_estado_idx');
            });
        } catch (\Throwable) {
            // Índices ya presentes.
        }

        $leidos = 0;
        $actualizados = 0;
        DB::table('pedido_bma_cajas')->orderBy('id')->chunkById(200, function ($filas) use (&$leidos, &$actualizados) {
            foreach ($filas as $fila) {
                $leidos++;
                if (! empty($fila->uuid_operativo)) {
                    continue;
                }
                DB::table('pedido_bma_cajas')->where('id', $fila->id)->update([
                    'uuid_operativo' => (string) Str::uuid(),
                    'estado_operativo' => $fila->estado_operativo ?: 'activa',
                    'moneda' => $fila->moneda ?: 'MXN',
                ]);
                $actualizados++;
            }
        });

        logger()->info('fase2_envios_cajas backfill uuid', [
            'leidos' => $leidos,
            'actualizados' => $actualizados,
        ]);

        Schema::table('pedido_bma_documentos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_bma_documentos', 'pedido_bma_caja_id')) {
                $table->foreignId('pedido_bma_caja_id')->nullable()->after('pedido_bma_id')
                    ->constrained('pedido_bma_cajas')->nullOnDelete();
            }
        });

        $docsLeidos = 0;
        $docsOk = 0;
        $docsHuerfanos = 0;
        DB::table('pedido_bma_documentos')
            ->where('relacion_tipo', 'envio_caja')
            ->whereNotNull('relacion_id')
            ->whereNull('pedido_bma_caja_id')
            ->orderBy('id')
            ->chunkById(200, function ($filas) use (&$docsLeidos, &$docsOk, &$docsHuerfanos) {
                foreach ($filas as $doc) {
                    $docsLeidos++;
                    $existe = DB::table('pedido_bma_cajas')->where('id', $doc->relacion_id)->exists();
                    if ($existe) {
                        DB::table('pedido_bma_documentos')->where('id', $doc->id)->update([
                            'pedido_bma_caja_id' => $doc->relacion_id,
                        ]);
                        $docsOk++;
                    } else {
                        $docsHuerfanos++;
                    }
                }
            });

        logger()->info('fase2_envios_cajas backfill documentos', [
            'leidos' => $docsLeidos,
            'vinculados' => $docsOk,
            'huerfanos' => $docsHuerfanos,
        ]);

        foreach (EnviosPedidoBmaConfig::semillas() as $clave => $meta) {
            ConfiguracionSistema::updateOrCreate(
                ['clave' => $clave],
                [
                    'valor' => $meta['valor'],
                    'tipo' => $meta['tipo'],
                    'grupo' => 'ControlPedidos',
                    'descripcion' => $meta['descripcion'],
                ]
            );
        }

        PermisoCatalogoMigracion::registrar([
            'control_pedidos.envios.retirar_caja',
            'control_pedidos.envios.reabrir_caja',
            'control_pedidos.envios.editar_costos_pago_validado',
        ]);
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'control_pedidos.envios.retirar_caja',
            'control_pedidos.envios.reabrir_caja',
            'control_pedidos.envios.editar_costos_pago_validado',
        ])->delete();

        ConfiguracionSistema::whereIn('clave', array_keys(EnviosPedidoBmaConfig::semillas()))->delete();

        Schema::table('pedido_bma_documentos', function (Blueprint $table) {
            if (Schema::hasColumn('pedido_bma_documentos', 'pedido_bma_caja_id')) {
                $table->dropConstrainedForeignId('pedido_bma_caja_id');
            }
        });

        Schema::table('pedido_bma_cajas', function (Blueprint $table) {
            try {
                $table->dropUnique('pedido_bma_cajas_pedido_uuid_unique');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('pedido_bma_cajas_pedido_estado_idx');
            } catch (\Throwable) {
            }
            if (Schema::hasColumn('pedido_bma_cajas', 'retirada_por_id')) {
                $table->dropConstrainedForeignId('retirada_por_id');
            }
            if (Schema::hasColumn('pedido_bma_cajas', 'costos_actualizados_por_id')) {
                $table->dropConstrainedForeignId('costos_actualizados_por_id');
            }
            $cols = array_values(array_filter([
                Schema::hasColumn('pedido_bma_cajas', 'uuid_operativo') ? 'uuid_operativo' : null,
                Schema::hasColumn('pedido_bma_cajas', 'costo_envio') ? 'costo_envio' : null,
                Schema::hasColumn('pedido_bma_cajas', 'costo_seguro') ? 'costo_seguro' : null,
                Schema::hasColumn('pedido_bma_cajas', 'costo_adicional') ? 'costo_adicional' : null,
                Schema::hasColumn('pedido_bma_cajas', 'concepto_adicional') ? 'concepto_adicional' : null,
                Schema::hasColumn('pedido_bma_cajas', 'moneda') ? 'moneda' : null,
                Schema::hasColumn('pedido_bma_cajas', 'estado_operativo') ? 'estado_operativo' : null,
                Schema::hasColumn('pedido_bma_cajas', 'retirada_at') ? 'retirada_at' : null,
                Schema::hasColumn('pedido_bma_cajas', 'motivo_retiro') ? 'motivo_retiro' : null,
                Schema::hasColumn('pedido_bma_cajas', 'costos_actualizados_at') ? 'costos_actualizados_at' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
