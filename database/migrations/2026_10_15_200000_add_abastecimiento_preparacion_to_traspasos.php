<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_traspasos', function (Blueprint $table) {
            $table->string('proposito', 40)->nullable()->index();
            $table->foreignId('tarea_receptora_id')->nullable()->constrained('pedido_bma_tareas_preparacion');
            $table->foreignId('pedido_receptor_id')->nullable()->constrained('pedidos_bma');
            $table->foreignId('almacen_destino_id')->nullable()->constrained('almacenes');
            $table->unsignedInteger('version_operativa')->default(1);
            $table->timestamp('salida_fisica_at')->nullable();
        });
        Schema::table('solicitud_traspaso_productos', function (Blueprint $table) {
            $table->foreignId('tarea_producto_receptor_id')->nullable()->constrained('pedido_bma_tarea_productos');
        });
        Schema::create('traspaso_operaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_traspaso_id')->constrained('solicitudes_traspasos');
            $table->string('tipo', 30);
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('actor_id')->constrained('users');
            $table->json('lineas');
            $table->text('observacion')->nullable();
            $table->string('evidencia_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traspaso_operaciones');
        Schema::table('solicitud_traspaso_productos', fn (Blueprint $table) => $table->dropConstrainedForeignId('tarea_producto_receptor_id'));
        Schema::table('solicitudes_traspasos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tarea_receptora_id');
            $table->dropConstrainedForeignId('pedido_receptor_id');
            $table->dropConstrainedForeignId('almacen_destino_id');
            $table->dropColumn(['proposito', 'version_operativa', 'salida_fisica_at']);
        });
    }
};
