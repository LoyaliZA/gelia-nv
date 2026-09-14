<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_precio_lotes', function (Blueprint $table) {
            $table->string('origen', 24)->default('calculo')->after('estado');
            $table->uuid('lote_origen_id')->nullable()->after('origen');
            $table->text('motivo_restauracion')->nullable()->after('lote_origen_id');
            $table->index(['store_id', 'origen'], 'tn_precio_lote_store_origen_idx');
            $table->foreign('lote_origen_id', 'tn_precio_lote_origen_fk')
                ->references('id')
                ->on('tiendanube_precio_lotes')
                ->nullOnDelete();
        });

        Schema::create('tiendanube_precio_conciliaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('lote_id');
            $table->unsignedBigInteger('revision_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('variante_id');
            $table->string('campo', 24);
            $table->string('valor_operacion', 32)->nullable();
            $table->string('valor_remoto', 32)->nullable();
            $table->string('valor_export', 32)->nullable();
            $table->string('evidencia_tipo', 32);
            $table->string('resultado', 24);
            $table->text('explicacion')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->timestamp('evidencia_at');
            $table->timestamps();

            $table->index(['lote_id', 'variante_id'], 'tn_precio_conc_lote_var_idx');
            $table->foreign('lote_id', 'tn_precio_conc_lote_fk')
                ->references('id')
                ->on('tiendanube_precio_lotes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_precio_conciliaciones');

        Schema::table('tiendanube_precio_lotes', function (Blueprint $table) {
            $table->dropForeign('tn_precio_lote_origen_fk');
            $table->dropIndex('tn_precio_lote_store_origen_idx');
            $table->dropColumn(['origen', 'lote_origen_id', 'motivo_restauracion']);
        });
    }
};
