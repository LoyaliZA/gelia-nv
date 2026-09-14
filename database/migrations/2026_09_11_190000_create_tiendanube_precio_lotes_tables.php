<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendanube_precio_lotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('store_id')->index();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('config_generation')->default(1);
            $table->string('api_version', 16);
            $table->string('motor_contract_version', 16);
            $table->string('moneda', 8)->default('MXN');
            $table->uuid('selection_id');
            $table->unsignedInteger('selection_version');
            $table->unsignedInteger('selection_generacion')->default(1);
            $table->string('selection_modo', 32);
            $table->json('selection_filtros');
            $table->string('estado', 24)->default('borrador');
            $table->unsignedInteger('revision_actual_numero')->default(1);
            $table->timestamp('fecha_lectura')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'estado']);
            $table->index('selection_id');
        });

        Schema::create('tiendanube_precio_lote_revisiones', function (Blueprint $table) {
            $table->id();
            $table->uuid('lote_id');
            $table->unsignedInteger('numero');
            $table->string('estado', 24)->default('borrador');
            $table->string('checksum', 64)->nullable();
            $table->string('huella_conflicto_remoto', 64)->nullable();
            $table->unsignedBigInteger('regla_id')->nullable();
            $table->unsignedBigInteger('regla_version_id')->nullable();
            $table->json('definicion');
            $table->json('resumen')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->timestamp('aprobado_at')->nullable();
            $table->timestamps();

            $table->unique(['lote_id', 'numero']);
            $table->foreign('lote_id')
                ->references('id')
                ->on('tiendanube_precio_lotes')
                ->cascadeOnDelete();
        });

        Schema::create('tiendanube_precio_lote_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('revision_id');
            $table->unsignedBigInteger('producto_id');
            $table->unsignedBigInteger('variante_id');
            $table->string('producto_nombre')->nullable();
            $table->string('variante_sku')->nullable();
            $table->json('variante_atributos')->nullable();
            $table->text('imagen_url')->nullable();
            $table->boolean('espejo_existe')->default(true);
            $table->json('valores_anteriores');
            $table->json('fuentes_snapshot');
            $table->json('resultado_calculado')->nullable();
            $table->json('ajustes_manuales')->nullable();
            $table->json('resultado_final')->nullable();
            $table->json('validaciones')->nullable();
            $table->json('errores')->nullable();
            $table->string('estado_fila', 24)->default('sin_cambio');
            $table->boolean('excluido')->default(false);
            $table->string('exclusion_motivo')->nullable();
            $table->timestamps();

            $table->unique(['revision_id', 'variante_id']);
            $table->index(['revision_id', 'estado_fila']);
            $table->index('variante_id');
            $table->foreign('revision_id')
                ->references('id')
                ->on('tiendanube_precio_lote_revisiones')
                ->cascadeOnDelete();
        });

        Schema::create('tiendanube_precio_lote_eventos', function (Blueprint $table) {
            $table->id();
            $table->uuid('lote_id');
            $table->unsignedBigInteger('revision_id')->nullable();
            $table->string('tipo', 48);
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lote_id', 'tipo']);
            $table->foreign('lote_id')
                ->references('id')
                ->on('tiendanube_precio_lotes')
                ->cascadeOnDelete();
        });

        Schema::create('tiendanube_precio_lote_simulaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('revision_id')->unique();
            $table->string('estado', 24)->default('pendiente');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('procesados')->default(0);
            $table->text('error')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('revision_id')
                ->references('id')
                ->on('tiendanube_precio_lote_revisiones')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_precio_lote_simulaciones');
        Schema::dropIfExists('tiendanube_precio_lote_eventos');
        Schema::dropIfExists('tiendanube_precio_lote_items');
        Schema::dropIfExists('tiendanube_precio_lote_revisiones');
        Schema::dropIfExists('tiendanube_precio_lotes');
    }
};
