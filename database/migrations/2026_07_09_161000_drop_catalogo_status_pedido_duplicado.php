<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina el catálogo duplicado catalogo_status_pedido.
 * El formulario usa catalogo_estatus_pedidos (relación estatus del pedido).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pedidos_bma', 'catalogo_status_pedido_id')) {
            Schema::table('pedidos_bma', function (Blueprint $table) {
                $table->dropConstrainedForeignId('catalogo_status_pedido_id');
            });
        }

        Schema::dropIfExists('catalogo_status_pedido');
    }

    public function down(): void
    {
        Schema::create('catalogo_status_pedido', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->foreignId('catalogo_status_pedido_id')->nullable()->after('cliente_id')->constrained('catalogo_status_pedido')->nullOnDelete();
        });
    }
};
