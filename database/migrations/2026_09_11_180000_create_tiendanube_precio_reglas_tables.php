<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendanube_precio_reglas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('habilitada')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('version_actual_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['store_id', 'archived_at']);
        });

        Schema::create('tiendanube_precio_regla_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regla_id')->constrained('tiendanube_precio_reglas')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('contract_version', 16);
            $table->json('definicion');
            $table->boolean('utilizable')->default(true);
            $table->string('motivo_no_utilizable')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['regla_id', 'numero']);
        });

        Schema::table('tiendanube_precio_reglas', function (Blueprint $table) {
            $table->foreign('version_actual_id')
                ->references('id')
                ->on('tiendanube_precio_regla_versiones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_precio_reglas', function (Blueprint $table) {
            $table->dropForeign(['version_actual_id']);
        });

        Schema::dropIfExists('tiendanube_precio_regla_versiones');
        Schema::dropIfExists('tiendanube_precio_reglas');
    }
};
