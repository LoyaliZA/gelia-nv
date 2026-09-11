<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tiendanube_producto_imagen_operaciones')) {
            Schema::create('tiendanube_producto_imagen_operaciones', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tienda_id');
                $table->unsignedBigInteger('producto_id')->index();
                $table->string('solicitud_clave');
                $table->string('archivo_hash', 64)->nullable();
                $table->string('modo', 32);
                $table->json('ids_originales')->nullable();
                $table->unsignedBigInteger('imagen_nueva_id')->nullable();
                $table->string('estado', 32);
                $table->json('eliminaciones')->nullable();
                $table->text('error')->nullable();
                $table->unsignedSmallInteger('intentos')->default(0);
                $table->string('archivo_path')->nullable();
                $table->string('src_url', 2048)->nullable();
                $table->string('filename')->nullable();
                $table->unsignedInteger('position')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tienda_id', 'solicitud_clave'], 'tn_img_op_tienda_solicitud_uq');
            });
        } elseif (! $this->tieneIndice('tiendanube_producto_imagen_operaciones', 'tn_img_op_tienda_solicitud_uq')) {
            Schema::table('tiendanube_producto_imagen_operaciones', function (Blueprint $table) {
                $table->unique(['tienda_id', 'solicitud_clave'], 'tn_img_op_tienda_solicitud_uq');
            });
        }

        if (Schema::hasTable('tiendanube_image_import_items') && ! Schema::hasColumn('tiendanube_image_import_items', 'operacion_id')) {
            Schema::table('tiendanube_image_import_items', function (Blueprint $table) {
                $table->uuid('operacion_id')->nullable()->after('imagen_tn_id');
            });
        }
    }

    private function tieneIndice(string $tabla, string $nombre): bool
    {
        foreach (Schema::getIndexes($tabla) as $indice) {
            if (($indice['name'] ?? null) === $nombre) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        Schema::table('tiendanube_image_import_items', function (Blueprint $table) {
            $table->dropColumn('operacion_id');
        });

        Schema::dropIfExists('tiendanube_producto_imagen_operaciones');
    }
};
