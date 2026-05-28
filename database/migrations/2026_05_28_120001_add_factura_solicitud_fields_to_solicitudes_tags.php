<?php

use App\Models\CatalogoProceso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->string('factura_razon_social')->nullable()->after('solicitar_cotizacion');
            $table->string('archivo_facturas_path')->nullable()->after('factura_razon_social');
        });

        CatalogoProceso::updateOrCreate(
            ['nombre' => 'SOLICITUD DE FACTURAS'],
            ['categoria_flujo' => CatalogoProceso::CATEGORIA_OPERATIVO]
        );
    }

    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropColumn(['factura_razon_social', 'archivo_facturas_path']);
        });

        CatalogoProceso::where('nombre', 'SOLICITUD DE FACTURAS')->delete();
    }
};
