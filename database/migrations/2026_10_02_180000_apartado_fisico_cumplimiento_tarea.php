<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = [
        'control_pedidos.tienda.apartado.ver',
        'control_pedidos.tienda.apartado.separar',
        'control_pedidos.tienda.apartado.confirmar_devolucion',
        'control_pedidos.tienda.apartado.aprobar_prorroga',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('pedido_bma_cumplimiento_fisico')) {
            Schema::create('pedido_bma_cumplimiento_fisico', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_bma_tarea_preparacion_id')
                    ->constrained('pedido_bma_tareas_preparacion', indexName: 'pb_cump_tarea_fk')
                    ->cascadeOnDelete();
                $table->string('estado', 32)->default('POR_SEPARAR');
                $table->unsignedInteger('cantidad')->default(0);
                $table->string('ubicacion', 160)->nullable();
                $table->timestamp('vence_at')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->boolean('prorroga_aplicada')->default(false);
                $table->foreignId('prorroga_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_cump_prorr_fk')
                    ->nullOnDelete();
                $table->string('prorroga_motivo', 500)->nullable();
                $table->timestamp('prorroga_at')->nullable();
                $table->timestamp('devolucion_solicitada_at')->nullable();
                $table->timestamp('devuelta_at')->nullable();
                $table->foreignId('devuelta_por_id')->nullable()
                    ->constrained('users', indexName: 'pb_cump_dev_por_fk')
                    ->nullOnDelete();
                $table->foreignId('documento_devolucion_id')->nullable()
                    ->constrained('pedido_bma_tarea_documentos', indexName: 'pb_cump_doc_fk')
                    ->nullOnDelete();
                $table->timestamps();

                $table->unique('pedido_bma_tarea_preparacion_id', 'pb_cump_tarea_uq');
                $table->index(['estado', 'vence_at'], 'pb_cump_estado_vence_idx');
            });
        }

        if (! Schema::hasTable('pedido_bma_cumplimiento_eventos')) {
            Schema::create('pedido_bma_cumplimiento_eventos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_bma_cumplimiento_fisico_id')
                    ->constrained('pedido_bma_cumplimiento_fisico', indexName: 'pb_cevt_cump_fk')
                    ->cascadeOnDelete();
                $table->foreignId('pedido_bma_tarea_preparacion_id')
                    ->constrained('pedido_bma_tareas_preparacion', indexName: 'pb_cevt_tarea_fk')
                    ->cascadeOnDelete();
                $table->string('tipo', 40);
                $table->foreignId('usuario_id')->nullable()
                    ->constrained('users', indexName: 'pb_cevt_user_fk')
                    ->nullOnDelete();
                $table->string('motivo', 500)->nullable();
                $table->string('idempotencia_clave', 120);
                $table->json('datos')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique('idempotencia_clave', 'pb_cevt_idem_uq');
                $table->index(['pedido_bma_tarea_preparacion_id', 'tipo'], 'pb_cevt_tarea_tipo_idx');
            });
        }

        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_cumplimiento_eventos');
        Schema::dropIfExists('pedido_bma_cumplimiento_fisico');
    }
};
