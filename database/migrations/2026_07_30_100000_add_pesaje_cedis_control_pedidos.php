<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->timestamp('pesaje_solicitado_at')->nullable()->after('estatus_envio');
            $table->timestamp('pesaje_respondido_at')->nullable()->after('pesaje_solicitado_at');
            $table->foreignId('pesaje_respondido_por_id')->nullable()->after('pesaje_respondido_at')
                ->constrained('users')->nullOnDelete();
            $table->string('motivo_repesaje', 40)->nullable()->after('pesaje_respondido_por_id');
        });

        Schema::create('pedido_bma_cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->foreignId('catalogo_tipo_caja_id')->constrained('catalogo_tipos_caja_pedido')->restrictOnDelete();
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['pedido_bma_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_cajas');

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pesaje_respondido_por_id');
            $table->dropColumn([
                'pesaje_solicitado_at',
                'pesaje_respondido_at',
                'motivo_repesaje',
            ]);
        });
    }
};
