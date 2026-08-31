<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = [
        'control_pedidos.tienda.trasladar',
    ];

    public function up(): void
    {
        Schema::table('pedido_bma_tareas_preparacion', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'enviada_cedis_por_id')) {
                $table->foreignId('enviada_cedis_por_id')->nullable()->after('atendida_at')
                    ->constrained('users', indexName: 'pb_tprep_env_cedis_fk')->nullOnDelete();
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'enviada_cedis_at')) {
                $table->timestamp('enviada_cedis_at')->nullable()->after('enviada_cedis_por_id');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'recibida_cedis_por_id')) {
                $table->foreignId('recibida_cedis_por_id')->nullable()->after('enviada_cedis_at')
                    ->constrained('users', indexName: 'pb_tprep_rec_cedis_fk')->nullOnDelete();
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'recibida_cedis_at')) {
                $table->timestamp('recibida_cedis_at')->nullable()->after('recibida_cedis_por_id');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'motivo_rechazo_cedis')) {
                $table->text('motivo_rechazo_cedis')->nullable()->after('recibida_cedis_at');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'intento_traslado')) {
                $table->unsignedSmallInteger('intento_traslado')->default(0)->after('motivo_rechazo_cedis');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'solicitud_traspaso_id')) {
                $table->unsignedBigInteger('solicitud_traspaso_id')->nullable()->after('intento_traslado');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'peso_real_kg')) {
                $table->decimal('peso_real_kg', 10, 3)->nullable()->after('observaciones_respuesta');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'peso_volumetrico_kg')) {
                $table->decimal('peso_volumetrico_kg', 10, 3)->nullable()->after('peso_real_kg');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'catalogo_tipo_caja_id')) {
                $table->unsignedBigInteger('catalogo_tipo_caja_id')->nullable()->after('peso_volumetrico_kg');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'observaciones_fisicas')) {
                $table->text('observaciones_fisicas')->nullable()->after('catalogo_tipo_caja_id');
            }
        });

        if (Schema::hasColumn('pedido_bma_tareas_preparacion', 'solicitud_traslado_id')) {
            // noop — typo guard
        }

        if (Schema::hasTable('solicitudes_traspasos') && ! Schema::hasColumn('solicitudes_traspasos', 'tarea_preparacion_id')) {
            Schema::table('solicitudes_traspasos', function (Blueprint $table) {
                $table->unsignedBigInteger('tarea_preparacion_id')->nullable()->after('id');
                $table->string('origen_codigo', 32)->nullable()->after('tarea_preparacion_id');
            });

            // Unique only when linked (MySQL: unique allows multiple NULLs).
            Schema::table('solicitudes_traspasos', function (Blueprint $table) {
                $table->unique('tarea_preparacion_id', 'sol_traspaso_tarea_prep_uq');
                $table->foreign('tarea_preparacion_id', 'sol_traspaso_tarea_prep_fk')
                    ->references('id')->on('pedido_bma_tareas_preparacion')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('pedido_bma_tareas_preparacion', 'solicitud_traspaso_id')) {
            $tieneFk = DB::getDriverName() !== 'sqlite' && collect(DB::select(
                "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'pedido_bma_tareas_preparacion'
                   AND CONSTRAINT_NAME = 'pb_tprep_sol_traspaso_fk'"
            ))->isNotEmpty();
            if (! $tieneFk) {
                Schema::table('pedido_bma_tareas_preparacion', function (Blueprint $table) {
                    $table->foreign('solicitud_traspaso_id', 'pb_tprep_sol_traspaso_fk')
                        ->references('id')->on('solicitudes_traspasos')->nullOnDelete();
                });
            }
        }

        // Snapshot sin catálogo cuando el origen es Gestión de pedido.
        if (Schema::hasTable('solicitud_traspaso_productos') && DB::getDriverName() !== 'sqlite') {
            $fk = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'solicitud_traspaso_productos'
                   AND COLUMN_NAME = 'producto_id'
                   AND REFERENCED_TABLE_NAME IS NOT NULL"
            ))->first();
            if ($fk && isset($fk->CONSTRAINT_NAME)) {
                DB::statement('ALTER TABLE `solicitud_traspaso_productos` DROP FOREIGN KEY `'.$fk->CONSTRAINT_NAME.'`');
            }
            DB::statement('ALTER TABLE `solicitud_traspaso_productos` MODIFY `producto_id` BIGINT UNSIGNED NULL');
            $tieneFk = collect(DB::select(
                "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'solicitud_traspaso_productos'
                   AND CONSTRAINT_NAME = 'sol_tp_prod_producto_fk'"
            ))->isNotEmpty();
            if (! $tieneFk) {
                Schema::table('solicitud_traspaso_productos', function (Blueprint $table) {
                    $table->foreign('producto_id', 'sol_tp_prod_producto_fk')
                        ->references('id')->on('productos')->nullOnDelete();
                });
            }
        }

        if (! Schema::hasTable('control_pedidos_matriz_requisitos_preparacion')) {
            Schema::create('control_pedidos_matriz_requisitos_preparacion', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_modalidad', 64);
                $table->string('departamento_codigo', 64)->nullable();
                $table->unsignedBigInteger('almacen_origen_id')->nullable();
                $table->string('destino_codigo', 32)->nullable();
                $table->string('tipo_integracion', 32)->nullable();
                $table->json('requisitos_json');
                $table->boolean('activo')->default(true);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();

                $table->index(['codigo_modalidad', 'activo'], 'cp_matriz_mod_activo_idx');
            });
        }

        $now = now();
        $modalidades = [
            [
                'codigo' => 'ENVIO_BODEGA_NORMAL',
                'nombre' => 'Envío a bodega',
                'descripcion' => 'Piezas localizadas en Tienda que se trasladan a CEDIS para integrar el envío.',
                'area_responsable_codigo' => 'TIENDA',
                'requisitos_json' => json_encode([
                    'evidencia_general_obligatoria' => true,
                    'evidencia_por_producto' => false,
                    'peso_real_obligatorio' => true,
                    'peso_volumetrico_obligatorio' => false,
                    'caja_obligatoria' => true,
                    'observaciones_fisicas_obligatorias' => true,
                    'traslado_cedis' => true,
                    'estados_fisicos_permitidos' => ['bueno', 'regular', 'malo', 'danado', 'sin_existencia'],
                ]),
                'activo' => true,
                'orden' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'codigo' => 'ENVIO_BODEGA_COMPLEMENTO',
                'nombre' => 'Envío a bodega (complemento)',
                'descripcion' => 'Traslado de piezas de Tienda vinculadas a un pedido complementario o principal.',
                'area_responsable_codigo' => 'TIENDA',
                'requisitos_json' => json_encode([
                    'evidencia_general_obligatoria' => true,
                    'evidencia_por_producto' => false,
                    'peso_real_obligatorio' => true,
                    'peso_volumetrico_obligatorio' => false,
                    'caja_obligatoria' => false,
                    'observaciones_fisicas_obligatorias' => false,
                    'traslado_cedis' => true,
                    'vinculo_pedido_principal' => true,
                    'estados_fisicos_permitidos' => ['bueno', 'regular', 'malo', 'danado', 'sin_existencia'],
                ]),
                'activo' => true,
                'orden' => 11,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($modalidades as $mod) {
            $exists = DB::table('catalogo_modalidades_preparacion_pedido')
                ->where('codigo', $mod['codigo'])
                ->exists();
            if (! $exists) {
                DB::table('catalogo_modalidades_preparacion_pedido')->insert($mod);
            }
        }

        $matriz = [
            [
                'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
                'departamento_codigo' => 'BELLAROMA',
                'almacen_origen_id' => null,
                'destino_codigo' => 'CEDIS',
                'tipo_integracion' => 'pedido_principal',
                'requisitos_json' => json_encode([
                    'evidencia_general_obligatoria' => true,
                    'peso_real_obligatorio' => true,
                    'peso_volumetrico_obligatorio' => false,
                    'caja_obligatoria' => true,
                    'observaciones_fisicas_obligatorias' => true,
                    'traslado_cedis' => true,
                ]),
                'activo' => true,
                'orden' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'codigo_modalidad' => 'ENVIO_BODEGA_COMPLEMENTO',
                'departamento_codigo' => 'BELLAROMA',
                'almacen_origen_id' => null,
                'destino_codigo' => 'CEDIS',
                'tipo_integracion' => 'complemento',
                'requisitos_json' => json_encode([
                    'evidencia_general_obligatoria' => true,
                    'peso_real_obligatorio' => true,
                    'peso_volumetrico_obligatorio' => false,
                    'caja_obligatoria' => false,
                    'observaciones_fisicas_obligatorias' => false,
                    'traslado_cedis' => true,
                    'vinculo_pedido_principal' => true,
                ]),
                'activo' => true,
                'orden' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
                'departamento_codigo' => 'CALL_CENTER',
                'almacen_origen_id' => null,
                'destino_codigo' => 'CEDIS',
                'tipo_integracion' => null,
                'requisitos_json' => json_encode([
                    'evidencia_general_obligatoria' => false,
                    'peso_real_obligatorio' => true,
                    'peso_volumetrico_obligatorio' => false,
                    'caja_obligatoria' => false,
                    'observaciones_fisicas_obligatorias' => false,
                    'traslado_cedis' => true,
                ]),
                'activo' => true,
                'orden' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'codigo_modalidad' => 'ENVIO_BODEGA_NORMAL',
                'departamento_codigo' => null,
                'almacen_origen_id' => null,
                'destino_codigo' => 'CEDIS',
                'tipo_integracion' => null,
                'requisitos_json' => json_encode([
                    'evidencia_general_obligatoria' => true,
                    'peso_real_obligatorio' => true,
                    'peso_volumetrico_obligatorio' => false,
                    'caja_obligatoria' => false,
                    'observaciones_fisicas_obligatorias' => false,
                    'traslado_cedis' => true,
                ]),
                'activo' => true,
                'orden' => 99,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        if (Schema::hasTable('control_pedidos_matriz_requisitos_preparacion')
            && DB::table('control_pedidos_matriz_requisitos_preparacion')->count() === 0) {
            DB::table('control_pedidos_matriz_requisitos_preparacion')->insert($matriz);
        }

        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::whereIn('name', self::PERMISOS)->delete();

        Schema::dropIfExists('control_pedidos_matriz_requisitos_preparacion');

        if (Schema::hasColumn('solicitudes_traspasos', 'tarea_preparacion_id')) {
            Schema::table('solicitudes_traspasos', function (Blueprint $table) {
                $table->dropForeign('sol_traspaso_tarea_prep_fk');
                $table->dropUnique('sol_traspaso_tarea_prep_uq');
                $table->dropColumn(['tarea_preparacion_id', 'origen_codigo']);
            });
        }

        if (Schema::hasColumn('pedido_bma_tareas_preparacion', 'solicitud_traspaso_id')) {
            Schema::table('pedido_bma_tareas_preparacion', function (Blueprint $table) {
                $table->dropForeign('pb_tprep_sol_traspaso_fk');
                $table->dropColumn([
                    'enviada_cedis_por_id',
                    'enviada_cedis_at',
                    'recibida_cedis_por_id',
                    'recibida_cedis_at',
                    'motivo_rechazo_cedis',
                    'intento_traslado',
                    'solicitud_traspaso_id',
                    'peso_real_kg',
                    'peso_volumetrico_kg',
                    'catalogo_tipo_caja_id',
                    'observaciones_fisicas',
                ]);
            });
        }

        DB::table('catalogo_modalidades_preparacion_pedido')
            ->whereIn('codigo', ['ENVIO_BODEGA_NORMAL', 'ENVIO_BODEGA_COMPLEMENTO'])
            ->delete();
    }
};
