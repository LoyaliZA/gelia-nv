<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_configuracion', function (Blueprint $table) {
            $table->unsignedBigInteger('config_generation')->default(1)->after('store_url');
        });

        Schema::table('tiendanube_sync_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('id');
            $table->unsignedBigInteger('config_generation')->nullable()->after('store_id');
            $table->string('fase')->nullable()->after('estado');
            $table->unsignedInteger('pagina_categorias')->default(0)->after('fase');
            $table->unsignedInteger('pagina_productos')->default(0)->after('pagina_categorias');
            $table->unsignedInteger('candidatos_categorias')->default(0)->after('eliminados_categorias');
            $table->unsignedInteger('candidatos_productos')->default(0)->after('candidatos_categorias');
            $table->unsignedInteger('pendientes_confirmacion')->default(0)->after('candidatos_productos');
            $table->boolean('confirmar_depuracion_masiva')->default(false)->after('pendientes_confirmacion');
            $table->index(['store_id', 'estado']);
        });

        Schema::create('tiendanube_sync_recursos_vistos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->constrained('tiendanube_sync_logs')->cascadeOnDelete();
            $table->string('tipo', 32);
            $table->unsignedBigInteger('recurso_id');
            $table->timestamps();

            $table->unique(['sync_log_id', 'tipo', 'recurso_id'], 'tn_sync_vistos_unicos');
            $table->index(['sync_log_id', 'tipo']);
        });

        Schema::create('tiendanube_operaciones_tienda', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->unique();
            $table->string('tipo', 64)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('config_generation')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable()->index();
            $table->string('estado', 32)->default('liberada');
            $table->timestamps();
        });

        Schema::table('tiendanube_webhook_deliveries', function (Blueprint $table) {
            $table->string('deferred_reason')->nullable()->after('error');
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'status']);
            $table->dropColumn('deferred_reason');
        });

        Schema::dropIfExists('tiendanube_operaciones_tienda');
        Schema::dropIfExists('tiendanube_sync_recursos_vistos');

        Schema::table('tiendanube_sync_logs', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'estado']);
            $table->dropColumn([
                'store_id',
                'config_generation',
                'fase',
                'pagina_categorias',
                'pagina_productos',
                'candidatos_categorias',
                'candidatos_productos',
                'pendientes_confirmacion',
                'confirmar_depuracion_masiva',
            ]);
        });

        Schema::table('tiendanube_configuracion', function (Blueprint $table) {
            $table->dropColumn('config_generation');
        });
    }
};
