<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendanube_precio_selecciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('store_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('generacion')->default(1);
            $table->string('modo', 32);
            $table->json('filtros');
            $table->unsignedInteger('total_variantes')->default(0);
            $table->unsignedInteger('total_productos')->default(0);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'store_id']);
        });

        Schema::create('tiendanube_precio_seleccion_miembros', function (Blueprint $table) {
            $table->uuid('seleccion_id');
            $table->unsignedBigInteger('variante_id');
            $table->timestamps();

            $table->primary(['seleccion_id', 'variante_id']);
            $table->index('variante_id');
            $table->foreign('seleccion_id')
                ->references('id')
                ->on('tiendanube_precio_selecciones')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_precio_seleccion_miembros');
        Schema::dropIfExists('tiendanube_precio_selecciones');
    }
};
