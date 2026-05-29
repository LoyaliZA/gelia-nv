<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_facturas', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('vendedor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('catalogo_estado_solicitud_id')->constrained('catalogo_estados_solicitud');
            $table->string('razon_social');
            $table->json('datos_fiscales')->nullable();
            $table->string('archivo_fiscal_path')->nullable();
            $table->text('observaciones_vendedor')->nullable();
            $table->text('motivo_respuesta')->nullable();
            $table->string('motivo_incorrecta')->nullable();
            $table->string('factura_pdf_path')->nullable();
            $table->string('factura_pdf_nombre')->nullable();
            $table->string('factura_xml_path')->nullable();
            $table->string('factura_xml_nombre')->nullable();
            $table->string('evidencia_error_path')->nullable();
            $table->foreignId('respondida_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('respondida_at')->nullable();
            $table->unsignedBigInteger('legacy_solicitud_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_facturas');
    }
};
