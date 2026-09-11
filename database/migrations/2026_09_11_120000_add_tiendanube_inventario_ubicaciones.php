<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_configuracion', function (Blueprint $table) {
            $table->string('locations_probe', 32)->nullable()->after('store_url');
            $table->boolean('multi_inventario_activo')->nullable()->after('locations_probe');
        });

        Schema::create('tiendanube_ubicaciones', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->unsignedBigInteger('store_id')->index();
            $table->json('name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('priority')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'activa']);
        });

        Schema::create('tiendanube_variante_niveles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variante_id');
            $table->string('ubicacion_id', 64);
            $table->integer('stock')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['variante_id', 'ubicacion_id']);
            $table->foreign('variante_id')
                ->references('id')
                ->on('tiendanube_producto_variantes')
                ->cascadeOnDelete();
            $table->foreign('ubicacion_id')
                ->references('id')
                ->on('tiendanube_ubicaciones')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_variante_niveles');
        Schema::dropIfExists('tiendanube_ubicaciones');

        Schema::table('tiendanube_configuracion', function (Blueprint $table) {
            $table->dropColumn(['locations_probe', 'multi_inventario_activo']);
        });
    }
};
