<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->boolean('confirmo_informacion_escalonamiento')->default(false)->after('catalogo_lista_descuento_id');
            $table->decimal('monto_final_tentativo', 12, 2)->nullable()->after('confirmo_informacion_escalonamiento');
            $table->decimal('total_proyectado_neto', 12, 2)->nullable()->after('monto_final_tentativo');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropColumn([
                'confirmo_informacion_escalonamiento',
                'monto_final_tentativo',
                'total_proyectado_neto',
            ]);
        });
    }
};
