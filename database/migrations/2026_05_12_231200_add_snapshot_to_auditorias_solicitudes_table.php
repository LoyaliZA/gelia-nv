<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias_solicitudes', function (Blueprint $table) {
            // Almacenará el estado exacto de la solicitud (monto, evidencia, proceso) en este punto del tiempo
            $table->json('datos_snapshot')->nullable()->after('motivo_reporte');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias_solicitudes', function (Blueprint $table) {
            $table->dropColumn('datos_snapshot');
        });
    }
};