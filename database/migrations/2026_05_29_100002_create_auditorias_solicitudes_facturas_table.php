<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias_solicitudes_facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_factura_id')->constrained('solicitudes_facturas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('estado_anterior_id')->nullable()->constrained('catalogo_estados_solicitud')->nullOnDelete();
            $table->foreignId('estado_nuevo_id')->nullable()->constrained('catalogo_estados_solicitud')->nullOnDelete();
            $table->text('motivo_reporte')->nullable();
            $table->json('datos_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_solicitudes_facturas');
    }
};
