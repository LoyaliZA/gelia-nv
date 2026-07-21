<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->boolean('guia_retraso')->default(false)->after('guia_subida_at');
            $table->timestamp('guia_corregida_at')->nullable()->after('guia_retraso');
            $table->foreignId('guia_corregida_por_id')->nullable()->after('guia_corregida_at')
                ->constrained('users')->nullOnDelete();

            $table->json('campos_incorrectos')->nullable()->after('incidencia_empaque_por_id');
            $table->text('detalle_error_datos')->nullable()->after('campos_incorrectos');
            $table->timestamp('error_datos_at')->nullable()->after('detalle_error_datos');
            $table->foreignId('error_datos_por_id')->nullable()->after('error_datos_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('error_datos_por_id');
            $table->dropColumn(['campos_incorrectos', 'detalle_error_datos', 'error_datos_at']);
            $table->dropConstrainedForeignId('guia_corregida_por_id');
            $table->dropColumn(['guia_retraso', 'guia_corregida_at']);
        });
    }
};
