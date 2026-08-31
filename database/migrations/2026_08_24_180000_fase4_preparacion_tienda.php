<?php

use App\Models\ConfiguracionSistema;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = [
        'control_pedidos.tienda.ver',
        'control_pedidos.tienda.tomar',
        'control_pedidos.tienda.responder',
        'control_pedidos.tienda.reportar_error',
        'control_pedidos.tienda.liberar',
        'control_pedidos.tienda.evidencias',
        'control_pedidos.preparacion.solicitar',
        'control_pedidos.preparacion.corregir',
    ];

    public function up(): void
    {
        // Recuperación: fallo previo por nombre FK > 64 chars dejó tabla sin constraints.
        if (Schema::hasTable('pedido_bma_tareas_preparacion') && DB::getDriverName() !== 'sqlite') {
            $tieneFkModalidad = collect(DB::select(
                'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = ?',
                ['pedido_bma_tareas_preparacion', 'pb_tprep_modalidad_fk', 'FOREIGN KEY']
            ))->isNotEmpty();

            if (! $tieneFkModalidad) {
                Schema::dropIfExists('pedido_bma_tarea_sesion_evidencia_fotos');
                Schema::dropIfExists('pedido_bma_tarea_sesiones_evidencia');
                Schema::dropIfExists('pedido_bma_tarea_historial');
                Schema::dropIfExists('pedido_bma_tarea_documentos');
                Schema::dropIfExists('pedido_bma_tarea_productos');
                Schema::dropIfExists('pedido_bma_tareas_preparacion');
            }
        }

        if (! Schema::hasTable('catalogo_modalidades_preparacion_pedido')) {
            Schema::create('catalogo_modalidades_preparacion_pedido', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 64)->unique();
                $table->string('nombre');
                $table->text('descripcion')->nullable();
                $table->string('area_responsable_codigo', 32)->default('TIENDA');
                $table->json('requisitos_json')->nullable();
                $table->boolean('activo')->default(true);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pedido_bma_tareas_preparacion')) {
            Schema::create('pedido_bma_tareas_preparacion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_bma_id')
                    ->constrained('pedidos_bma', indexName: 'pb_tprep_pedido_fk')
                    ->cascadeOnDelete();
                $table->foreignId('catalogo_modalidad_preparacion_id')
                    ->constrained('catalogo_modalidades_preparacion_pedido', indexName: 'pb_tprep_modalidad_fk')
                    ->restrictOnDelete();
                $table->foreignId('almacen_id')
                    ->constrained('almacenes', indexName: 'pb_tprep_almacen_fk')
                    ->restrictOnDelete();
                $table->string('area_responsable_codigo', 32)->default('TIENDA');
                $table->string('estado', 32)->default('PENDIENTE');
                $table->foreignId('solicitada_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_tprep_sol_por_fk')
                    ->nullOnDelete();
                $table->timestamp('solicitada_at')->nullable();
                $table->foreignId('asignada_a_id')->nullable()
                    ->constrained('users', indexName: 'pb_tprep_asig_a_fk')
                    ->nullOnDelete();
                $table->foreignId('atendida_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_tprep_atend_fk')
                    ->nullOnDelete();
                $table->timestamp('atendida_at')->nullable();
                $table->timestamp('fecha_limite')->nullable();
                $table->text('observaciones_solicitud')->nullable();
                $table->text('observaciones_respuesta')->nullable();
                $table->boolean('requiere_traslado_cedis')->default(false);
                $table->foreignId('tarea_anterior_id')->nullable()
                    ->constrained('pedido_bma_tareas_preparacion', indexName: 'pb_tprep_ant_fk')
                    ->nullOnDelete();
                $table->string('idempotencia_clave', 64)->nullable()->unique();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['estado', 'almacen_id']);
                $table->index(['pedido_bma_id', 'estado']);
            });
        }

        if (! Schema::hasTable('pedido_bma_tarea_productos')) {
            Schema::create('pedido_bma_tarea_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_tarea_preparacion_id')
                ->constrained('pedido_bma_tareas_preparacion', indexName: 'pb_tp_tarea_fk')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->string('sku', 64)->nullable();
            $table->string('descripcion_snapshot');
            $table->unsignedInteger('cantidad_solicitada')->default(1);
            $table->unsignedInteger('cantidad_encontrada')->nullable();
            $table->string('estado_fisico', 32)->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('pedido_bma_tarea_documentos')) {
            Schema::create('pedido_bma_tarea_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_tarea_preparacion_id')
                ->constrained('pedido_bma_tareas_preparacion', indexName: 'pb_td_tarea_fk')
                ->cascadeOnDelete();
            $table->foreignId('pedido_bma_tarea_producto_id')->nullable()
                ->constrained('pedido_bma_tarea_productos', indexName: 'pb_td_producto_fk')
                ->nullOnDelete();
            $table->string('tipo_evidencia', 32);
            $table->string('ruta_interna');
            $table->string('nombre_original');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->string('hash_sha256', 64)->nullable();
            $table->foreignId('subido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('subido_at')->nullable();
            $table->boolean('inmutable')->default(false);
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('pedido_bma_tarea_historial')) {
            Schema::create('pedido_bma_tarea_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_tarea_preparacion_id')
                ->constrained('pedido_bma_tareas_preparacion', indexName: 'pb_th_tarea_fk')
                ->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado_anterior', 32)->nullable();
            $table->string('estado_nuevo', 32);
            $table->string('accion', 64);
            $table->text('comentario')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('pedido_bma_tarea_sesiones_evidencia')) {
            Schema::create('pedido_bma_tarea_sesiones_evidencia', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_bma_tarea_preparacion_id')
                    ->constrained('pedido_bma_tareas_preparacion', indexName: 'pb_tse_tarea_fk')
                    ->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->string('codigo_publico', 16)->unique();
                $table->string('estado', 20)->default('pendiente');
                $table->timestamp('expira_en');
                $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
                $table->timestamp('reclamado_en')->nullable();
                $table->string('claim_ip', 45)->nullable();
                $table->string('claim_ua', 500)->nullable();
                $table->json('snapshot_json')->nullable();
                $table->json('tipos_evidencia_json')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pedido_bma_tarea_sesion_evidencia_fotos')) {
            Schema::create('pedido_bma_tarea_sesion_evidencia_fotos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_bma_tarea_sesion_evidencia_id')
                    ->constrained('pedido_bma_tarea_sesiones_evidencia', indexName: 'pb_tsef_sesion_fk')
                    ->cascadeOnDelete();
                $table->string('ruta');
                $table->string('nombre_original')->nullable();
                $table->string('mime_type', 128)->nullable();
                $table->unsignedBigInteger('tamano_bytes')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
            });
        }

        $now = now();
        if (DB::table('catalogo_modalidades_preparacion_pedido')->whereIn('codigo', ['RECOGE_TIENDA', 'RECOGE_TIENDA_TRANSFERENCIA'])->count() < 2) {
            DB::table('catalogo_modalidades_preparacion_pedido')->insertOrIgnore([
            [
                'codigo' => 'RECOGE_TIENDA',
                'nombre' => 'Recoge en tienda',
                'descripcion' => 'El cliente recoge mercancía en el almacén de tienda seleccionado.',
                'area_responsable_codigo' => 'TIENDA',
                'requisitos_json' => json_encode([
                    'evidencia_general_obligatoria' => true,
                    'evidencia_por_producto' => false,
                    'estados_fisicos_permitidos' => ['bueno', 'regular', 'malo', 'danado', 'sin_existencia'],
                ]),
                'activo' => true,
                'orden' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'codigo' => 'RECOGE_TIENDA_TRANSFERENCIA',
                'nombre' => 'Recoge en tienda (transferencia)',
                'descripcion' => 'Recolección local con pago por transferencia; mercancía queda resguardada hasta entrega.',
                'area_responsable_codigo' => 'TIENDA',
                'requisitos_json' => json_encode([
                    'evidencia_general_obligatoria' => true,
                    'evidencia_por_producto' => false,
                    'estados_fisicos_permitidos' => ['bueno', 'regular', 'malo', 'danado', 'sin_existencia'],
                    'resguardo' => true,
                ]),
                'activo' => true,
                'orden' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ]);
        }

        PermisoCatalogoMigracion::registrar(self::PERMISOS);

        ConfiguracionSistema::updateOrCreate(
            ['clave' => PreparacionTiendaConfig::CLAVE_FLAG],
            [
                'valor' => json_encode(PreparacionTiendaConfig::flagPorDefecto(), JSON_UNESCAPED_UNICODE),
                'tipo' => 'json',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Feature flag preparación Tienda (Control Pedidos)',
            ]
        );

        ConfiguracionSistema::updateOrCreate(
            ['clave' => PreparacionTiendaConfig::CLAVE_DIAS_RESGUARDO],
            [
                'valor' => '3',
                'tipo' => 'integer',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Días de resguardo para transferencia en tienda',
            ]
        );

        ConfiguracionSistema::updateOrCreate(
            ['clave' => PreparacionTiendaConfig::CLAVE_RECORDATORIO_HORA],
            [
                'valor' => '11:00',
                'tipo' => 'string',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Hora local del recordatorio de vencimiento de resguardo',
            ]
        );

        ConfiguracionSistema::updateOrCreate(
            ['clave' => PreparacionTiendaConfig::CLAVE_ZONA_HORARIA],
            [
                'valor' => 'America/Mexico_City',
                'tipo' => 'string',
                'grupo' => 'ControlPedidos',
                'descripcion' => 'Zona horaria operativa para plazos de preparación Tienda',
            ]
        );
    }

    public function down(): void
    {
        ConfiguracionSistema::whereIn('clave', [
            PreparacionTiendaConfig::CLAVE_FLAG,
            PreparacionTiendaConfig::CLAVE_DIAS_RESGUARDO,
            PreparacionTiendaConfig::CLAVE_RECORDATORIO_HORA,
            PreparacionTiendaConfig::CLAVE_ZONA_HORARIA,
        ])->delete();

        Permission::whereIn('name', self::PERMISOS)->delete();

        Schema::dropIfExists('pedido_bma_tarea_sesion_evidencia_fotos');
        Schema::dropIfExists('pedido_bma_tarea_sesiones_evidencia');
        Schema::dropIfExists('pedido_bma_tarea_historial');
        Schema::dropIfExists('pedido_bma_tarea_documentos');
        Schema::dropIfExists('pedido_bma_tarea_productos');
        Schema::dropIfExists('pedido_bma_tareas_preparacion');
        Schema::dropIfExists('catalogo_modalidades_preparacion_pedido');
    }
};
