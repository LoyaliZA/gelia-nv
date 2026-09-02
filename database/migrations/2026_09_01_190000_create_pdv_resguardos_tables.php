<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdv_resguardos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_id')->nullable()->constrained('pedidos_bma')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('almacen_id')->nullable()->constrained('almacenes')->nullOnDelete();
            $table->string('estado', 32);
            $table->unsignedSmallInteger('cantidad_bultos_esperada')->default(0);
            $table->timestamp('salida_cedis_at')->nullable();
            $table->timestamp('recepcion_fisica_at')->nullable();
            $table->timestamp('entrega_completada_at')->nullable();
            $table->timestamp('devolucion_confirmada_at')->nullable();
            $table->timestamp('vencido_repuesto_at')->nullable();
            $table->boolean('entrega_bloqueada')->default(false);
            $table->string('snapshot_folio', 64)->nullable();
            $table->string('snapshot_cliente_nombre')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['pedido_bma_id', 'sucursal_id'], 'pdv_resguardos_pedido_sucursal_unique');
            $table->index(['sucursal_id', 'estado'], 'pdv_resguardos_sucursal_estado_idx');
            $table->index('salida_cedis_at');
            $table->index('recepcion_fisica_at');
        });

        Schema::create('pdv_resguardo_bultos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resguardo_id')->constrained('pdv_resguardos')->restrictOnDelete();
            $table->foreignId('pedido_bma_id')->nullable()->constrained('pedidos_bma')->restrictOnDelete();
            $table->string('folio', 64)->nullable();
            $table->string('tipo', 16)->default('caja');
            $table->string('estado', 32);
            $table->timestamp('recepcion_at')->nullable();
            $table->foreignId('recepcion_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entrega_at')->nullable();
            $table->timestamp('devolucion_salida_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['resguardo_id', 'folio'], 'pdv_bultos_resguardo_folio_unique');
            $table->index(['resguardo_id', 'estado'], 'pdv_bultos_resguardo_estado_idx');
        });

        Schema::create('pdv_resguardo_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resguardo_id')->constrained('pdv_resguardos')->restrictOnDelete();
            $table->foreignId('bulto_id')->nullable()->constrained('pdv_resguardo_bultos')->nullOnDelete();
            $table->string('tipo_evento', 64);
            $table->string('estado_anterior', 32)->nullable();
            $table->string('estado_nuevo', 32)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ocurrido_at');
            $table->json('snapshot_json')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();

            $table->unique('idempotency_key', 'pdv_eventos_idempotency_unique');
            $table->index(['resguardo_id', 'ocurrido_at'], 'pdv_eventos_resguardo_ocurrido_idx');
        });

        Schema::create('pdv_resguardo_incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resguardo_id')->constrained('pdv_resguardos')->restrictOnDelete();
            $table->foreignId('bulto_id')->nullable()->constrained('pdv_resguardo_bultos')->nullOnDelete();
            $table->string('tipo', 32);
            $table->string('estado', 32);
            $table->text('descripcion');
            $table->foreignId('reportado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reportado_at');
            $table->foreignId('autorizado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('autorizado_at')->nullable();
            $table->text('motivo_autorizacion')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique('idempotency_key', 'pdv_incidencias_idempotency_unique');
            $table->index(['resguardo_id', 'estado'], 'pdv_incidencias_resguardo_estado_idx');
        });

        Schema::create('pdv_resguardo_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resguardo_id')->constrained('pdv_resguardos')->restrictOnDelete();
            $table->foreignId('pedido_bma_id')->nullable()->constrained('pedidos_bma')->restrictOnDelete();
            $table->string('relacion', 16);
            $table->string('nombre_quien_retira');
            $table->foreignId('entregado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entregado_at');
            $table->foreignId('incidencia_autorizada_id')->nullable()
                ->constrained('pdv_resguardo_incidencias')
                ->nullOnDelete();
            $table->json('snapshot_json')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique('idempotency_key', 'pdv_entregas_idempotency_unique');
            $table->index(['resguardo_id', 'entregado_at'], 'pdv_entregas_resguardo_at_idx');
        });

        Schema::create('pdv_resguardo_entrega_bultos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('pdv_resguardo_entregas')->restrictOnDelete();
            $table->foreignId('bulto_id')->constrained('pdv_resguardo_bultos')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['entrega_id', 'bulto_id'], 'pdv_entrega_bultos_unique');
        });

        Schema::create('pdv_resguardo_evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resguardo_id')->constrained('pdv_resguardos')->restrictOnDelete();
            $table->foreignId('evento_id')->nullable()->constrained('pdv_resguardo_eventos')->nullOnDelete();
            $table->foreignId('bulto_id')->nullable()->constrained('pdv_resguardo_bultos')->nullOnDelete();
            $table->foreignId('incidencia_id')->nullable()->constrained('pdv_resguardo_incidencias')->nullOnDelete();
            $table->foreignId('entrega_id')->nullable()->constrained('pdv_resguardo_entregas')->nullOnDelete();
            $table->string('tipo', 16);
            $table->string('ruta_interna');
            $table->string('nombre_original');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->string('hash_sha256', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('capturado_at');
            $table->boolean('inmutable')->default(true);
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['resguardo_id', 'tipo'], 'pdv_evidencias_resguardo_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_resguardo_evidencias');
        Schema::dropIfExists('pdv_resguardo_entrega_bultos');
        Schema::dropIfExists('pdv_resguardo_entregas');
        Schema::dropIfExists('pdv_resguardo_incidencias');
        Schema::dropIfExists('pdv_resguardo_eventos');
        Schema::dropIfExists('pdv_resguardo_bultos');
        Schema::dropIfExists('pdv_resguardos');
    }
};
