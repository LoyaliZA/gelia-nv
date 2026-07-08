<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones_almacen_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 32);
            $table->foreignId('almacen_id')->nullable()->constrained('almacenes')->nullOnDelete();
            $table->string('archivo_ruta');
            $table->string('archivo_normalizado')->nullable();
            $table->json('mapping');
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('procesados')->default(0);
            $table->unsignedInteger('importados')->default(0);
            $table->unsignedInteger('actualizados')->default(0);
            $table->unsignedInteger('omitidos')->default(0);
            $table->string('estado', 32)->default('pendiente');
            $table->text('mensaje_error')->nullable();
            $table->string('reporte_errores_token', 64)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index('tipo');
        });

        Schema::create('importaciones_almacen_errores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_almacen_log_id')
                ->constrained('importaciones_almacen_logs')
                ->cascadeOnDelete();
            $table->unsignedInteger('fila');
            $table->string('referencia')->default('—');
            $table->string('campo')->default('general');
            $table->text('mensaje');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones_almacen_errores');
        Schema::dropIfExists('importaciones_almacen_logs');
    }
};
