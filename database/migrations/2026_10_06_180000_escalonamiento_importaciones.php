<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('documentos_venta', 'remision_original')) {
            Schema::table('documentos_venta', function (Blueprint $table) {
                $table->string('remision_original')->nullable()->after('origen');
            });
        }

        if (! Schema::hasColumn('escalonamiento_incidencias', 'codigo')) {
            Schema::table('escalonamiento_incidencias', function (Blueprint $table) {
                $table->string('codigo', 64)->nullable()->after('gravedad');
            });
        }

        if (! Schema::hasColumn('escalonamiento_incidencias', 'user_id')) {
            Schema::table('escalonamiento_incidencias', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('estado')
                    ->constrained('users', indexName: 'esc_incidencia_user_fk')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('escalonamiento_importaciones')) {
            Schema::create('escalonamiento_importaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('escalonamiento_periodo_id')
                    ->constrained('escalonamiento_periodos', indexName: 'esc_importacion_periodo_fk');
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users', indexName: 'esc_importacion_user_fk')
                    ->nullOnDelete();
                $table->string('nombre_archivo');
                $table->string('hash', 64);
                $table->string('ruta')->nullable();
                $table->string('estado')->default('previsualizada');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('escalonamiento_importacion_filas')) {
            Schema::create('escalonamiento_importacion_filas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('escalonamiento_importacion_id')
                    ->constrained('escalonamiento_importaciones', indexName: 'esc_imp_fila_importacion_fk')
                    ->cascadeOnDelete();
                $table->unsignedInteger('numero_fila');
                $table->string('resultado');
                $table->text('motivo')->nullable();
                $table->json('interpretacion');
                $table->timestamps();
            });
        } elseif (! $this->tieneLlave('escalonamiento_importacion_filas', 'esc_imp_fila_importacion_fk')) {
            Schema::table('escalonamiento_importacion_filas', function (Blueprint $table) {
                $table->foreign('escalonamiento_importacion_id', 'esc_imp_fila_importacion_fk')
                    ->references('id')
                    ->on('escalonamiento_importaciones')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('documentos_venta_revisiones')) {
            Schema::create('documentos_venta_revisiones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('documento_venta_id')
                    ->constrained('documentos_venta', indexName: 'esc_revision_documento_fk');
                $table->decimal('total_anterior', 14, 2);
                $table->decimal('total_nuevo', 14, 2);
                $table->decimal('efecto_anterior', 14, 2);
                $table->decimal('efecto_nuevo', 14, 2);
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users', indexName: 'esc_revision_user_fk')
                    ->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_venta_revisiones');
        Schema::dropIfExists('escalonamiento_importacion_filas');
        Schema::dropIfExists('escalonamiento_importaciones');

        if (Schema::hasColumn('escalonamiento_incidencias', 'user_id')) {
            Schema::table('escalonamiento_incidencias', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasColumn('escalonamiento_incidencias', 'codigo')) {
            Schema::table('escalonamiento_incidencias', function (Blueprint $table) {
                $table->dropColumn('codigo');
            });
        }

        if (Schema::hasColumn('documentos_venta', 'remision_original')) {
            Schema::table('documentos_venta', function (Blueprint $table) {
                $table->dropColumn('remision_original');
            });
        }
    }

    private function tieneLlave(string $tabla, string $nombre): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return false;
        }

        $filas = Schema::getConnection()->select(
            'select CONSTRAINT_NAME from information_schema.TABLE_CONSTRAINTS where TABLE_SCHEMA = database() and TABLE_NAME = ? and CONSTRAINT_NAME = ?',
            [$tabla, $nombre],
        );

        return $filas !== [];
    }
};
