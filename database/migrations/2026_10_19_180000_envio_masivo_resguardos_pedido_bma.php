<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->boolean('envio_solo_resguardos')->default(false)->after('es_resguardo');
            $table->text('notas_resguardos_externos')->nullable()->after('envio_solo_resguardos');
        });

        Schema::create('pedido_bma_envios_resguardo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_envio_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->foreignId('pedido_resguardo_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('pedido_resguardo_id', 'pb_envio_resguardo_unico');
            $table->index('pedido_envio_id', 'pb_envio_resguardo_envio_idx');
        });

        Schema::table('pedido_bma_anexos_envio', function (Blueprint $table) {
            $table->string('tipo', 32)->default('diferido')->after('pedido_bma_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_bma_anexos_envio', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });

        Schema::dropIfExists('pedido_bma_envios_resguardo');

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropColumn(['envio_solo_resguardos', 'notas_resguardos_externos']);
        });
    }
};
