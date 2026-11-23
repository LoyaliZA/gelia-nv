<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clasificación estructural del uso de un saldo a favor (S-06). Solo esquema: no reclasifica ningún crédito existente ni
 * cambia montos, estados, reservas o aplicaciones. Los créditos actuales quedan «general» sin clasificación confirmada;
 * su conciliación es un paso aparte (`php artisan saf:conciliacion-envio`), con reporte previo de impacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saf_creditos', function (Blueprint $table) {
            $table->string('uso_permitido', 32)->default('general')->after('estado_revision');
            $table->string('origen_economico', 32)->nullable()->after('uso_permitido');
            $table->string('clasificacion_fuente', 40)->nullable()->after('origen_economico');
            $table->text('clasificacion_motivo')->nullable()->after('clasificacion_fuente');
            $table->foreignId('clasificado_por_id')->nullable()->after('clasificacion_motivo')->constrained('users')->nullOnDelete();
            $table->timestamp('clasificado_at')->nullable()->after('clasificado_por_id');
            $table->index('uso_permitido');
        });
    }

    public function down(): void
    {
        Schema::table('saf_creditos', function (Blueprint $table) {
            $table->dropIndex(['uso_permitido']);
            $table->dropConstrainedForeignId('clasificado_por_id');
            $table->dropColumn(['uso_permitido', 'origen_economico', 'clasificacion_fuente', 'clasificacion_motivo', 'clasificado_at']);
        });
    }
};
