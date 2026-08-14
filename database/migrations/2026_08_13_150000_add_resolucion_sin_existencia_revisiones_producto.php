<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_revisiones_producto', function (Blueprint $table) {
            $table->unsignedBigInteger('producto_id')->nullable()->after('descripcion_producto');
            $table->string('sku', 64)->nullable()->after('producto_id');
            $table->string('resolucion', 32)->nullable()->after('mejor_ejemplar');
            $table->text('resolucion_nota')->nullable()->after('resolucion');
            $table->foreignId('resolucion_por_id')->nullable()->after('resolucion_nota')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('resolucion_at')->nullable()->after('resolucion_por_id');

            $table->index(['pedido_bma_id', 'estado_fisico', 'resolucion'], 'rev_prod_sin_ex_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_bma_revisiones_producto', function (Blueprint $table) {
            $table->dropIndex('rev_prod_sin_ex_idx');
            $table->dropConstrainedForeignId('resolucion_por_id');
            $table->dropColumn([
                'producto_id',
                'sku',
                'resolucion',
                'resolucion_nota',
                'resolucion_at',
            ]);
        });
    }
};
