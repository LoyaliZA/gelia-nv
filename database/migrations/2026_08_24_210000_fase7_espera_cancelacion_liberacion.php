<?php

use App\Models\ConfiguracionSistema;
use App\Services\ControlPedidos\CancelacionOperativaConfig;
use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = [
        'control_pedidos.espera_pago',
        'control_pedidos.cancelacion_operativa.solicitar',
        'control_pedidos.cancelacion_operativa.reactivar',
        'control_pedidos.cancelacion_operativa.resolver_financiera',
        'control_pedidos.cancelacion_operativa.concluir_admin',
        'control_pedidos.cedis.liberar',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('pedidos_bma', 'esperando_pago_at')) {
            Schema::table('pedidos_bma', function (Blueprint $table) {
                $table->timestamp('esperando_pago_at')->nullable()->after('cancelado_at');
            });
        }

        if (Schema::hasTable('pedido_bma_tareas_preparacion')) {
            Schema::table('pedido_bma_tareas_preparacion', function (Blueprint $table) {
                if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'espera_pago_at')) {
                    $table->timestamp('espera_pago_at')->nullable()->after('fecha_limite');
                }
                if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'regla_plazo_snapshot')) {
                    $table->json('regla_plazo_snapshot')->nullable()->after('espera_pago_at');
                }
            });
        }

        if (! Schema::hasTable('pedido_bma_cancelaciones_operativas')) {
            Schema::create('pedido_bma_cancelaciones_operativas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_bma_id')
                    ->constrained('pedidos_bma', indexName: 'pb_canc_op_pedido_fk')
                    ->cascadeOnDelete();
                $table->string('estado', 32)->default('SOLICITADA');
                $table->string('motivo', 64);
                $table->text('comentario')->nullable();
                $table->foreignId('solicitada_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_canc_op_sol_fk')
                    ->nullOnDelete();
                $table->timestamp('solicitada_at')->nullable();
                $table->foreignId('liberacion_solicitada_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_canc_op_lib_sol_fk')
                    ->nullOnDelete();
                $table->timestamp('liberacion_solicitada_at')->nullable();
                $table->foreignId('liberada_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_canc_op_lib_fk')
                    ->nullOnDelete();
                $table->timestamp('liberada_at')->nullable();
                $table->foreignId('revertida_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_canc_op_rev_fk')
                    ->nullOnDelete();
                $table->timestamp('revertida_at')->nullable();
                $table->text('motivo_reactivacion')->nullable();
                $table->foreignId('finalizada_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_canc_op_fin_fk')
                    ->nullOnDelete();
                $table->timestamp('finalizada_at')->nullable();
                $table->string('folio_anterior', 64)->nullable();
                $table->string('folio_nuevo', 64)->nullable();
                $table->string('resolucion_financiera', 64)->nullable();
                $table->boolean('requiere_resolucion_financiera')->default(false);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();

                $table->index(['pedido_bma_id', 'estado'], 'pb_canc_op_pedido_estado_idx');
                $table->index('estado', 'pb_canc_op_estado_idx');
            });
        }

        if (! Schema::hasTable('pedido_bma_cancelacion_operativa_tareas')) {
            Schema::create('pedido_bma_cancelacion_operativa_tareas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_bma_cancelacion_operativa_id')
                    ->constrained('pedido_bma_cancelaciones_operativas', indexName: 'pb_canc_op_t_canc_fk')
                    ->cascadeOnDelete();
                $table->foreignId('pedido_bma_tarea_preparacion_id')
                    ->constrained('pedido_bma_tareas_preparacion', indexName: 'pb_canc_op_t_tarea_fk')
                    ->cascadeOnDelete();
                $table->string('estado_liberacion', 32)->default('PENDIENTE');
                $table->string('estado_previo_liberacion', 32)->nullable();
                $table->unsignedInteger('cantidad_a_liberar')->nullable();
                $table->unsignedInteger('cantidad_liberada')->nullable();
                $table->text('incidencia')->nullable();
                $table->json('evidencia_meta')->nullable();
                $table->foreignId('liberada_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_canc_op_t_lib_fk')
                    ->nullOnDelete();
                $table->timestamp('liberada_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();

                $table->unique(
                    ['pedido_bma_cancelacion_operativa_id', 'pedido_bma_tarea_preparacion_id'],
                    'pb_canc_op_t_unica'
                );
            });
        }

        if (! Schema::hasTable('pedido_bma_alertas_preparacion')) {
            Schema::create('pedido_bma_alertas_preparacion', function (Blueprint $table) {
                $table->id();
                $table->string('clave_unica', 128)->unique();
                $table->unsignedBigInteger('pedido_bma_id')->nullable();
                $table->unsignedBigInteger('pedido_bma_tarea_preparacion_id')->nullable();
                $table->string('tipo', 64);
                $table->string('ventana', 64)->nullable();
                $table->json('destinatarios')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('ejecutada_at')->nullable();
                $table->timestamps();
            });
        }

        PermisoCatalogoMigracion::registrar(self::PERMISOS);

        ConfiguracionSistema::updateOrCreate(
            ['clave' => CancelacionOperativaConfig::CLAVE_FLAG],
            [
                'valor' => json_encode(CancelacionOperativaConfig::flagPorDefecto(), JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Feature flag cancelación operativa / espera de pago (Fase 7)',
            ]
        );

        ConfiguracionSistema::updateOrCreate(
            ['clave' => CancelacionOperativaConfig::CLAVE_DIAS_TIPO],
            [
                'valor' => 'naturales',
                'tipo' => 'string',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Tipo de días para plazo de resguardo: naturales|habiles',
            ]
        );

        ConfiguracionSistema::updateOrCreate(
            ['clave' => CancelacionOperativaConfig::CLAVE_ANTICIPACION_AVISO_HORAS],
            [
                'valor' => '24',
                'tipo' => 'integer',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Horas de anticipación para aviso cercano al vencimiento de espera',
            ]
        );

        ConfiguracionSistema::updateOrCreate(
            ['clave' => CancelacionOperativaConfig::CLAVE_ROL_RESOLUTOR],
            [
                'valor' => 'control_pedidos.cancelacion_operativa.resolver_financiera',
                'tipo' => 'string',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Permiso/rol resolutor financiero de cancelación operativa',
            ]
        );

        ConfiguracionSistema::updateOrCreate(
            ['clave' => CancelacionOperativaConfig::CLAVE_EVIDENCIA_LIBERAR],
            [
                'valor' => 'opcional',
                'tipo' => 'string',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Evidencia al liberar mercancía: opcional|obligatoria',
            ]
        );
    }

    public function down(): void
    {
        ConfiguracionSistema::whereIn('clave', [
            CancelacionOperativaConfig::CLAVE_FLAG,
            CancelacionOperativaConfig::CLAVE_DIAS_TIPO,
            CancelacionOperativaConfig::CLAVE_ANTICIPACION_AVISO_HORAS,
            CancelacionOperativaConfig::CLAVE_ROL_RESOLUTOR,
            CancelacionOperativaConfig::CLAVE_EVIDENCIA_LIBERAR,
        ])->delete();

        Permission::whereIn('name', self::PERMISOS)->delete();

        Schema::dropIfExists('pedido_bma_alertas_preparacion');
        Schema::dropIfExists('pedido_bma_cancelacion_operativa_tareas');
        Schema::dropIfExists('pedido_bma_cancelaciones_operativas');

        if (Schema::hasTable('pedido_bma_tareas_preparacion')) {
            Schema::table('pedido_bma_tareas_preparacion', function (Blueprint $table) {
                if (Schema::hasColumn('pedido_bma_tareas_preparacion', 'regla_plazo_snapshot')) {
                    $table->dropColumn('regla_plazo_snapshot');
                }
                if (Schema::hasColumn('pedido_bma_tareas_preparacion', 'espera_pago_at')) {
                    $table->dropColumn('espera_pago_at');
                }
            });
        }

        if (Schema::hasColumn('pedidos_bma', 'esperando_pago_at')) {
            Schema::table('pedidos_bma', function (Blueprint $table) {
                $table->dropColumn('esperando_pago_at');
            });
        }
    }
};
