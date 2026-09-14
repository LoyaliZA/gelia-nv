<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendanube_precio_listas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('nombre', 120);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('tiendanube_precio_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('estado', 32)->default('revision');
            $table->string('delimiter', 8)->default(',');
            $table->string('decimal_sep', 8)->default('.');
            $table->json('mapeo_json')->nullable();
            $table->string('archivo_path')->nullable();
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('validas')->default(0);
            $table->unsignedInteger('errores')->default(0);
            $table->text('mensaje_error')->nullable();
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tiendanube_precio_fuente_versiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('tipo', 32);
            $table->unsignedBigInteger('lista_id')->nullable()->index();
            $table->string('fuente_clave', 40);
            $table->unsignedBigInteger('variante_id')->index();
            $table->unsignedBigInteger('producto_id')->index();
            $table->unsignedInteger('version');
            $table->char('moneda', 3);
            $table->decimal('valor_decimal', 12, 2);
            $table->timestamp('fecha');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origen', 16);
            $table->string('motivo', 255)->nullable();
            $table->unsignedBigInteger('import_id')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['store_id', 'fuente_clave', 'variante_id', 'version'],
                'tn_precio_fuente_ver_unique'
            );

            $table->foreign('lista_id')
                ->references('id')
                ->on('tiendanube_precio_listas')
                ->nullOnDelete();

            $table->foreign('import_id')
                ->references('id')
                ->on('tiendanube_precio_imports')
                ->nullOnDelete();
        });

        Schema::create('tiendanube_precio_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('tiendanube_precio_imports')->cascadeOnDelete();
            $table->unsignedInteger('fila');
            $table->string('sku', 255)->nullable();
            $table->unsignedBigInteger('variante_id')->nullable()->index();
            $table->unsignedBigInteger('producto_id')->nullable()->index();
            $table->string('destino_tipo', 32);
            $table->unsignedBigInteger('lista_id')->nullable();
            $table->string('valor_raw', 64)->nullable();
            $table->decimal('valor_decimal', 12, 2)->nullable();
            $table->char('moneda', 3)->nullable();
            $table->string('estado', 24)->default('error');
            $table->string('motivo', 64)->nullable();
            $table->decimal('valor_anterior', 12, 2)->nullable();
            $table->char('moneda_anterior', 3)->nullable();
            $table->boolean('seleccionado')->default(false);
            $table->json('candidatos_json')->nullable();
            $table->string('mensaje', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_precio_import_items');
        Schema::dropIfExists('tiendanube_precio_fuente_versiones');
        Schema::dropIfExists('tiendanube_precio_imports');
        Schema::dropIfExists('tiendanube_precio_listas');
    }
};
