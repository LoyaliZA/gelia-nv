<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->unsignedBigInteger('revision_registro')->default(0);
            $table->string('captura_creacion_clave', 64)->nullable()->unique();
            $table->string('captura_creacion_hash', 64)->nullable();
            $table->string('captura_creacion_base', 64)->nullable();
        });
        Schema::table('pedido_bma_tareas_preparacion', fn (Blueprint $table) => $table->string('solicitud_hash', 64)->nullable());
    }
    public function down(): void
    {
        Schema::table('pedido_bma_tareas_preparacion', fn (Blueprint $table) => $table->dropColumn('solicitud_hash'));
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropUnique(['captura_creacion_clave']);
            $table->dropColumn(['revision_registro', 'captura_creacion_clave', 'captura_creacion_hash', 'captura_creacion_base']);
        });
    }
};
