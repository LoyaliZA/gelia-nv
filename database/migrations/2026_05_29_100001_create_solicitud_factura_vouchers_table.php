<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_factura_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_factura_id')->constrained('solicitudes_facturas')->cascadeOnDelete();
            $table->string('path');
            $table->string('nombre_original');
            $table->string('mime')->nullable();
            $table->unsignedTinyInteger('orden')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_factura_vouchers');
    }
};
