<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multienvios_nacionales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pedido_origen_id')->unique()->constrained('pedidos_bma');
            $table->string('revision_consulta', 64);
            $table->string('estado', 40)->index();
            $table->foreignId('decidido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidido_at')->nullable();
            $table->foreignId('activado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activado_at')->nullable();
            $table->json('snapshot_origen_json')->nullable();
            $table->json('borrador_json')->nullable();
            $table->json('regularizacion_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('pedidos_bma')
            && Schema::hasColumn('pedidos_bma', 'multienvio_nacional_id')
            && DB::table('pedidos_bma')->whereNotNull('multienvio_nacional_id')->exists()) {
            throw new RuntimeException('No se puede revertir: hay pedidos asignados a multienvíos nacionales.');
        }

        Schema::dropIfExists('multienvios_nacionales');
    }
};
