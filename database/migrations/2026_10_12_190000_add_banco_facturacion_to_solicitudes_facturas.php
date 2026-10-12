<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_bancos', function (Blueprint $table) {
            $table->boolean('facturacion_habilitada')->default(true);
            $table->string('motivo_bloqueo_facturacion', 500)->nullable();
        });
        Schema::table('solicitudes_facturas', function (Blueprint $table) {
            $table->foreignId('catalogo_banco_id')->nullable()->constrained('catalogo_bancos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_facturas', fn (Blueprint $table) => $table->dropConstrainedForeignId('catalogo_banco_id'));
        Schema::table('catalogo_bancos', fn (Blueprint $table) => $table->dropColumn(['facturacion_habilitada', 'motivo_bloqueo_facturacion']));
    }
};
