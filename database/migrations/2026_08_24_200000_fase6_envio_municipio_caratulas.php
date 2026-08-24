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
        'control_pedidos.preparacion.destinatario',
        'control_pedidos.tienda.ver_identificacion',
        'control_pedidos.tienda.cargar_identificacion',
        'control_pedidos.tienda.generar_caratula',
        'control_pedidos.tienda.imprimir_caratula',
        'control_pedidos.tienda.regenerar_caratula',
        'control_pedidos.tienda.confirmar_caratula',
    ];

    public function up(): void
    {
        Schema::table('catalogo_paqueterias_pedido', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'requiere_caratula')) {
                $table->boolean('requiere_caratula')->default(false)->after('activo');
            }
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'requiere_identificacion')) {
                $table->boolean('requiere_identificacion')->default(false)->after('requiere_caratula');
            }
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'requiere_remision')) {
                $table->boolean('requiere_remision')->default(false)->after('requiere_identificacion');
            }
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'permite_por_cobrar')) {
                $table->boolean('permite_por_cobrar')->default(false)->after('requiere_remision');
            }
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'requiere_peso')) {
                $table->boolean('requiere_peso')->default(false)->after('permite_por_cobrar');
            }
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'requiere_caja')) {
                $table->boolean('requiere_caja')->default(false)->after('requiere_peso');
            }
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'requiere_evidencia_conjunto')) {
                $table->boolean('requiere_evidencia_conjunto')->default(false)->after('requiere_caja');
            }
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'campos_destino_obligatorios')) {
                $table->json('campos_destino_obligatorios')->nullable()->after('requiere_evidencia_conjunto');
            }
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'plantilla_caratula')) {
                $table->string('plantilla_caratula', 64)->nullable()->after('campos_destino_obligatorios');
            }
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'habilitado_envio_municipio')) {
                $table->boolean('habilitado_envio_municipio')->default(false)->after('plantilla_caratula');
            }
            if (! Schema::hasColumn('catalogo_paqueterias_pedido', 'reglas_municipio_pendientes')) {
                $table->boolean('reglas_municipio_pendientes')->default(true)->after('habilitado_envio_municipio');
            }
        });

        Schema::table('pedido_bma_tareas_preparacion', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'destinatario_nombre')) {
                $table->string('destinatario_nombre', 255)->nullable()->after('observaciones_fisicas');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'destinatario_telefono')) {
                $table->string('destinatario_telefono', 40)->nullable()->after('destinatario_nombre');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'municipio_destino')) {
                $table->string('municipio_destino', 255)->nullable()->after('destinatario_telefono');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'direccion_referencia')) {
                $table->string('direccion_referencia', 500)->nullable()->after('municipio_destino');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'catalogo_paqueteria_id')) {
                $table->unsignedBigInteger('catalogo_paqueteria_id')->nullable()->after('direccion_referencia');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'modalidad_cobro')) {
                $table->string('modalidad_cobro', 20)->nullable()->after('catalogo_paqueteria_id');
            }
            if (! Schema::hasColumn('pedido_bma_tareas_preparacion', 'destinatario_es_cliente')) {
                $table->boolean('destinatario_es_cliente')->default(true)->after('modalidad_cobro');
            }
        });

        if (Schema::hasColumn('pedido_bma_tareas_preparacion', 'catalogo_paqueteria_id')) {
            $tieneFk = collect(DB::select(
                "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'pedido_bma_tareas_preparacion'
                   AND CONSTRAINT_NAME = 'pb_tprep_paq_fk'"
            ))->isNotEmpty();
            if (! $tieneFk && Schema::hasTable('catalogo_paqueterias_pedido')) {
                Schema::table('pedido_bma_tareas_preparacion', function (Blueprint $table) {
                    $table->foreign('catalogo_paqueteria_id', 'pb_tprep_paq_fk')
                        ->references('id')->on('catalogo_paqueterias_pedido')->nullOnDelete();
                });
            }
        }

        if (! Schema::hasTable('pedido_bma_caratulas')) {
            Schema::create('pedido_bma_caratulas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pedido_bma_tarea_preparacion_id');
                $table->unsignedBigInteger('pedido_bma_id');
                $table->unsignedSmallInteger('version')->default(1);
                $table->string('destinatario_nombre', 255);
                $table->string('destinatario_telefono', 40);
                $table->string('municipio_destino', 255);
                $table->string('direccion_referencia', 500)->nullable();
                $table->unsignedBigInteger('catalogo_paqueteria_id');
                $table->string('modalidad_cobro', 20);
                $table->unsignedBigInteger('documento_identificacion_id')->nullable();
                $table->unsignedBigInteger('documento_remision_id')->nullable();
                $table->string('ruta_pdf', 500)->nullable();
                $table->string('hash_sha256', 64)->nullable();
                $table->string('estado', 20)->default('PENDIENTE');
                $table->unsignedBigInteger('generada_por_id')->nullable();
                $table->timestamp('generada_at')->nullable();
                $table->unsignedBigInteger('colocada_por_id')->nullable();
                $table->timestamp('colocada_at')->nullable();
                $table->text('motivo_regeneracion')->nullable();
                $table->timestamps();

                $table->unique(['pedido_bma_tarea_preparacion_id', 'version'], 'pb_caratula_tarea_ver_uq');
                $table->foreign('pedido_bma_tarea_preparacion_id', 'pb_caratula_tarea_fk')
                    ->references('id')->on('pedido_bma_tareas_preparacion')->cascadeOnDelete();
                $table->foreign('pedido_bma_id', 'pb_caratula_pedido_fk')
                    ->references('id')->on('pedidos_bma')->cascadeOnDelete();
                $table->foreign('catalogo_paqueteria_id', 'pb_caratula_paq_fk')
                    ->references('id')->on('catalogo_paqueterias_pedido')->restrictOnDelete();
                $table->foreign('documento_identificacion_id', 'pb_caratula_doc_id_fk')
                    ->references('id')->on('pedido_bma_tarea_documentos')->nullOnDelete();
                $table->foreign('documento_remision_id', 'pb_caratula_doc_rem_fk')
                    ->references('id')->on('pedido_bma_tarea_documentos')->nullOnDelete();
                $table->foreign('generada_por_id', 'pb_caratula_gen_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('colocada_por_id', 'pb_caratula_col_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        $now = now();
        $exists = DB::table('catalogo_modalidades_preparacion_pedido')
            ->where('codigo', 'ENVIO_MUNICIPIO')
            ->exists();
        if (! $exists) {
            DB::table('catalogo_modalidades_preparacion_pedido')->insert([
                'codigo' => 'ENVIO_MUNICIPIO',
                'nombre' => 'Envío a municipio',
                'descripcion' => 'Preparación en Tienda con carátula para transporte local/municipal.',
                'area_responsable_codigo' => 'TIENDA',
                'requisitos_json' => json_encode([
                    'evidencia_general_obligatoria' => true,
                    'evidencia_por_producto' => false,
                    'peso_real_obligatorio' => false,
                    'peso_volumetrico_obligatorio' => false,
                    'caja_obligatoria' => false,
                    'observaciones_fisicas_obligatorias' => false,
                    'traslado_cedis' => false,
                    'caratula' => true,
                    'estados_fisicos_permitidos' => ['bueno', 'regular', 'malo', 'danado', 'sin_existencia'],
                ]),
                'activo' => true,
                'orden' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('control_pedidos_matriz_requisitos_preparacion')) {
            $matrizExists = DB::table('control_pedidos_matriz_requisitos_preparacion')
                ->where('codigo_modalidad', 'ENVIO_MUNICIPIO')
                ->whereNull('departamento_codigo')
                ->exists();
            if (! $matrizExists) {
                DB::table('control_pedidos_matriz_requisitos_preparacion')->insert([
                    'codigo_modalidad' => 'ENVIO_MUNICIPIO',
                    'departamento_codigo' => null,
                    'almacen_origen_id' => null,
                    'destino_codigo' => 'MUNICIPIO',
                    'tipo_integracion' => null,
                    'requisitos_json' => json_encode([
                        'evidencia_general_obligatoria' => true,
                        'peso_real_obligatorio' => false,
                        'caja_obligatoria' => false,
                        'traslado_cedis' => false,
                        'caratula' => true,
                    ]),
                    'activo' => true,
                    'orden' => 50,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        \Spatie\Permission\Models\Permission::whereIn('name', self::PERMISOS)->delete();

        Schema::dropIfExists('pedido_bma_caratulas');

        if (Schema::hasColumn('pedido_bma_tareas_preparacion', 'catalogo_paqueteria_id')) {
            Schema::table('pedido_bma_tareas_preparacion', function (Blueprint $table) {
                $table->dropForeign('pb_tprep_paq_fk');
                $table->dropColumn([
                    'destinatario_nombre',
                    'destinatario_telefono',
                    'municipio_destino',
                    'direccion_referencia',
                    'catalogo_paqueteria_id',
                    'modalidad_cobro',
                    'destinatario_es_cliente',
                ]);
            });
        }

        Schema::table('catalogo_paqueterias_pedido', function (Blueprint $table) {
            foreach ([
                'requiere_caratula',
                'requiere_identificacion',
                'requiere_remision',
                'permite_por_cobrar',
                'requiere_peso',
                'requiere_caja',
                'requiere_evidencia_conjunto',
                'campos_destino_obligatorios',
                'plantilla_caratula',
                'habilitado_envio_municipio',
                'reglas_municipio_pendientes',
            ] as $col) {
                if (Schema::hasColumn('catalogo_paqueterias_pedido', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        DB::table('catalogo_modalidades_preparacion_pedido')->where('codigo', 'ENVIO_MUNICIPIO')->delete();
        DB::table('control_pedidos_matriz_requisitos_preparacion')->where('codigo_modalidad', 'ENVIO_MUNICIPIO')->delete();
    }
};
