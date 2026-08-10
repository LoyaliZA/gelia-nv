<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->string('estado_fisico_general', 32)->nullable()->after('pesaje_respondido_por_id');
            $table->text('comentario_fisico_general')->nullable()->after('estado_fisico_general');
            $table->boolean('tiene_observaciones_fisicas')->default(false)->after('comentario_fisico_general');
        });

        Schema::create('pedido_bma_revisiones_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('descripcion_producto', 255);
            $table->string('estado_fisico', 32);
            $table->text('comentario')->nullable();
            $table->boolean('unica_pieza')->default(false);
            $table->boolean('mejor_ejemplar')->default(false);
            $table->timestamps();

            $table->index(['pedido_bma_id', 'orden']);
        });

        Schema::table('pedido_bma_documentos', function (Blueprint $table) {
            $table->string('comentario', 500)->nullable()->after('orden');
            $table->string('relacion_tipo', 64)->nullable()->after('comentario');
            $table->unsignedBigInteger('relacion_id')->nullable()->after('relacion_tipo');
            $table->index(['relacion_tipo', 'relacion_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pedido_bma_documentos', function (Blueprint $table) {
            $table->dropIndex(['relacion_tipo', 'relacion_id']);
            $table->dropColumn(['comentario', 'relacion_tipo', 'relacion_id']);
        });

        Schema::dropIfExists('pedido_bma_revisiones_producto');

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropColumn([
                'estado_fisico_general',
                'comentario_fisico_general',
                'tiene_observaciones_fisicas',
            ]);
        });
    }
};
