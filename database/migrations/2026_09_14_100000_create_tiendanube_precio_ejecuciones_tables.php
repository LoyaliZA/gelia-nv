<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendanube_precio_ejecuciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lote_id');
            $table->unsignedBigInteger('revision_id');
            $table->unsignedBigInteger('store_id')->index();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('config_generation')->default(1);
            $table->string('api_version', 16);
            $table->string('checksum_revision', 64);
            $table->string('canal', 16)->default('api');
            $table->string('estado', 24)->default('pendiente');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('pendientes')->default(0);
            $table->unsignedInteger('procesando')->default(0);
            $table->unsignedInteger('confirmadas')->default(0);
            $table->unsignedInteger('conflictos')->default(0);
            $table->unsignedInteger('por_verificar')->default(0);
            $table->unsignedInteger('fallidas')->default(0);
            $table->unsignedInteger('canceladas')->default(0);
            $table->json('resumen_campos')->nullable();
            $table->string('lease_token', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->string('error_codigo', 64)->nullable();
            $table->text('error_mensaje')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['revision_id', 'canal'], 'tn_precio_ej_rev_canal_uidx');
            $table->index(['store_id', 'estado'], 'tn_precio_ej_store_estado_idx');
            $table->foreign('lote_id')
                ->references('id')
                ->on('tiendanube_precio_lotes')
                ->cascadeOnDelete();
            $table->foreign('revision_id')
                ->references('id')
                ->on('tiendanube_precio_lote_revisiones')
                ->cascadeOnDelete();
        });

        Schema::create('tiendanube_precio_ejecucion_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('ejecucion_id');
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('variante_id');
            $table->string('producto_nombre')->nullable();
            $table->string('variante_sku')->nullable();
            $table->json('variante_atributos')->nullable();
            $table->text('imagen_url')->nullable();
            $table->json('campos_objetivo');
            $table->json('valor_aprobado');
            $table->json('valor_anterior')->nullable();
            $table->json('valor_remoto_previo')->nullable();
            $table->json('valor_confirmado')->nullable();
            $table->string('estado', 32)->default('pendiente');
            $table->unsignedInteger('intentos')->default(0);
            $table->string('lease_token', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('siguiente_intento_at')->nullable();
            $table->string('error_codigo', 64)->nullable();
            $table->text('error_mensaje')->nullable();
            $table->json('evidencia')->nullable();
            $table->timestamps();

            $table->unique(['ejecucion_id', 'variante_id'], 'tn_precio_ej_item_var_uidx');
            $table->index(['ejecucion_id', 'estado'], 'tn_precio_ej_item_estado_idx');
            $table->foreign('ejecucion_id')
                ->references('id')
                ->on('tiendanube_precio_ejecuciones')
                ->cascadeOnDelete();
        });

        Schema::create('tiendanube_precio_ejecucion_eventos', function (Blueprint $table) {
            $table->id();
            $table->uuid('ejecucion_id');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('tipo', 48);
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['ejecucion_id', 'tipo'], 'tn_precio_ej_evt_tipo_idx');
            $table->foreign('ejecucion_id')
                ->references('id')
                ->on('tiendanube_precio_ejecuciones')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_precio_ejecucion_eventos');
        Schema::dropIfExists('tiendanube_precio_ejecucion_items');
        Schema::dropIfExists('tiendanube_precio_ejecuciones');
    }
};
