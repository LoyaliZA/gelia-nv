<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitud_beneficios')) {
            return;
        }

        Schema::create('solicitud_beneficios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('solicitud_tag_id')->constrained('solicitudes_tags');
            $table->string('campo', 20);
            $table->string('valor_anterior', 32)->nullable();
            $table->string('valor_aplicado', 32)->nullable();
            $table->string('estado', 20);
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'campo', 'estado'], 'sol_benef_cliente_campo_estado');
            $table->index(['solicitud_tag_id', 'campo', 'estado'], 'sol_benef_solicitud_campo_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_beneficios');
    }
};
