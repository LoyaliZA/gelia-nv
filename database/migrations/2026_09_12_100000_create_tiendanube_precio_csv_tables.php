<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendanube_precio_csv_perfiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('estado', 24)->default('borrador');
            $table->json('encabezados_canonicos');
            $table->string('delimiter', 4)->default(',');
            $table->string('decimal_sep', 4)->default('.');
            $table->string('encoding', 16)->default('UTF-8');
            $table->json('presets');
            $table->string('preset_default', 32)->default('solo_precios');
            $table->string('plantilla_path')->nullable();
            $table->timestamp('plantilla_fecha')->nullable();
            $table->string('contract_version', 32)->default('tn-csv-2026-09');
            $table->unsignedBigInteger('validado_por')->nullable();
            $table->timestamp('validado_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'version']);
        });

        Schema::create('tiendanube_precio_csv_artefactos', function (Blueprint $table) {
            $table->id();
            $table->uuid('lote_id')->nullable()->index();
            $table->unsignedBigInteger('revision_id')->nullable()->index();
            $table->string('revision_checksum', 64)->nullable();
            $table->unsignedBigInteger('perfil_id');
            $table->unsignedInteger('perfil_version');
            $table->unsignedBigInteger('store_id')->index();
            $table->string('preset_usado', 32);
            $table->json('columnas_exportadas');
            $table->string('columnas_hash', 64);
            $table->string('estado', 32)->default('generado');
            $table->string('archivo_path');
            $table->string('nombre_archivo');
            $table->string('hash_sha256', 64);
            $table->unsignedInteger('filas_exportadas')->default(0);
            $table->unsignedInteger('filas_con_cambio_precio')->default(0);
            $table->unsignedInteger('filas_contexto')->default(0);
            $table->unsignedInteger('particion')->default(1);
            $table->unsignedInteger('particiones_total')->default(1);
            $table->unsignedBigInteger('generado_por')->nullable();
            $table->timestamp('generado_at')->nullable();
            $table->timestamp('descargado_at')->nullable();
            $table->timestamp('importacion_declarada_at')->nullable();
            $table->timestamps();

            $table->index(['lote_id', 'revision_checksum', 'columnas_hash'], 'tn_precio_csv_art_lote_rev_cols_idx');
            $table->foreign('perfil_id')
                ->references('id')
                ->on('tiendanube_precio_csv_perfiles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_precio_csv_artefactos');
        Schema::dropIfExists('tiendanube_precio_csv_perfiles');
    }
};
